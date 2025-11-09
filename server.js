import 'dotenv/config';
import fs from 'node:fs';
import path from 'node:path';
import express from 'express';
import morgan from 'morgan';
import ExcelJS from 'exceljs';

const PORT = process.env.PORT || 3000;
const ROOT = process.cwd();
const DATA_DIR = path.join(ROOT, 'data');
const XLSX_FILE = path.join(DATA_DIR, 'pases.xlsx');
const SHEET_NAME = 'Pases';

fs.mkdirSync(DATA_DIR, { recursive: true });

/** Crea el archivo y hoja si no existen, con encabezados */
async function ensureWorkbook() {
  const wb = new ExcelJS.Workbook();
  if (fs.existsSync(XLSX_FILE)) {
    await wb.xlsx.readFile(XLSX_FILE);
    let ws = wb.getWorksheet(SHEET_NAME);
    if (!ws) {
      ws = wb.addWorksheet(SHEET_NAME);
      ws.addRow(['timestamp', 'para', 'pases', 'id', 'link', 'user']);
    }
    return { wb, ws: wb.getWorksheet(SHEET_NAME) };
  } else {
    const ws = wb.addWorksheet(SHEET_NAME);
    ws.addRow(['timestamp', 'para', 'pases', 'id', 'link', 'user']);
    await wb.xlsx.writeFile(XLSX_FILE);
    return { wb, ws };
  }
}

/** Lee todas las filas como objetos */
async function readAll() {
  const { wb } = await ensureWorkbook();
  const ws = wb.getWorksheet(SHEET_NAME);
  const rows = [];
  ws.eachRow((row, rowNumber) => {
    if (rowNumber === 1) return; // encabezados
    const [timestamp, para, pases, id, link, user] = row.values.slice(1);
    if (
      (timestamp ?? '') === '' &&
      (para ?? '') === '' &&
      (pases ?? '') === '' &&
      (id ?? '') === '' &&
      (link ?? '') === '' &&
      (user ?? '') === ''
    ) return;
    rows.push({ timestamp, para, pases, id, link, user });
  });
  // Más recientes primero
  rows.sort((a, b) => String(b.timestamp).localeCompare(String(a.timestamp)));
  return rows;
}

/** Cola simple para serializar escrituras (evita corrupción) */
let lastWrite = Promise.resolve();
function enqueueWrite(fn) {
  lastWrite = lastWrite.then(fn).catch(() => {}).then(() => {});
  return lastWrite;
}

/** Agrega una fila y guarda */
async function appendRow({ timestamp, para, pases, id, link, user }) {
  const { wb } = await ensureWorkbook();
  const ws = wb.getWorksheet(SHEET_NAME);
  ws.addRow([timestamp, para, pases, id, link, user]);
  await wb.xlsx.writeFile(XLSX_FILE);
}

/* ================== Express ================== */
const app = express();
app.use(morgan('dev'));
app.use(express.json({ limit: '1mb' }));
app.use(express.static(path.join(ROOT, 'public'))); // sirve admin.html/index.html

app.get('/api/health', (_req, res) => res.json({ ok: true }));

app.get('/api/pases', async (_req, res) => {
  try {
    const data = await readAll();
    res.json({ ok: true, data });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'read_error' });
  }
});

app.post('/api/pases', async (req, res) => {
  const { para, pases, id, link, user } = req.body || {};
  if (!para || !link || !pases) {
    return res.status(400).json({ ok: false, error: 'bad_request' });
  }
  try {
    await enqueueWrite(() =>
      appendRow({
        timestamp: new Date().toISOString(),
        para: String(para),
        pases: Number(pases),
        id: id ? String(id) : '',
        link: String(link),
        user: user ? String(user) : ''
      })
    );
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'write_error' });
  }
});

app.use('/api', (_req, res) => res.status(404).json({ ok: false, error: 'not_found' }));

app.listen(PORT, () => {
  console.log(`Servidor escuchando en http://localhost:${PORT}`);
  console.log(`Archivo Excel: ${XLSX_FILE}`);
});
