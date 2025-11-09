// server.js
import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import { Pool } from 'pg';
import ExcelJS from 'exceljs';

const app = express();
app.use(cors());
app.use(express.json());

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false }, // requerido por Supabase
});

// crea tabla si no existe (una vez por arranque)
let ensured = false;
async function ensureTable() {
  if (ensured) return;
  await pool.query(`
    CREATE TABLE IF NOT EXISTS pases (
      id SERIAL PRIMARY KEY,
      created_at TIMESTAMPTZ DEFAULT NOW(),
      para TEXT NOT NULL,
      pases INT NOT NULL,
      ref_id TEXT NOT NULL,
      link TEXT NOT NULL,
      usuario TEXT
    );
  `);
  ensured = true;
}

// GET /api/pases  -> lista registros
app.get('/api/pases', async (req, res) => {
  try {
    await ensureTable();
    const { rows } = await pool.query(`
      SELECT created_at AS "timestamp", para, pases, ref_id AS id, link, usuario AS "user"
      FROM pases
      ORDER BY created_at DESC
    `);
    res.json({ ok: true, data: rows });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'server_error' });
  }
});

// POST /api/pases  -> inserta registro
app.post('/api/pases', async (req, res) => {
  try {
    await ensureTable();
    const { para, pases, id, link, user } = req.body || {};
    if (!para || !id || !link || !Number.isInteger(Number(pases))) {
      return res.status(400).json({ ok: false, error: 'bad_request' });
    }
    await pool.query(
      `INSERT INTO pases (para, pases, ref_id, link, usuario) VALUES ($1,$2,$3,$4,$5)`,
      [para, Number(pases), id, link, user || null]
    );
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'server_error' });
  }
});

// GET /api/export-excel  -> descarga Excel con los registros
app.get('/api/export-excel', async (req, res) => {
  try {
    await ensureTable();
    const { rows } = await pool.query(`
      SELECT created_at AS "timestamp", para, pases, ref_id AS id, link, usuario AS "user"
      FROM pases
      ORDER BY created_at DESC
    `);

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Pases');
    ws.addRow(['timestamp','para','pases','id','link','user']);
    rows.forEach(r => ws.addRow([
      r.timestamp, r.para, r.pases, r.id, r.link, r.user || ''
    ]));

    res.setHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition','attachment; filename="pases.xlsx"');
    await wb.xlsx.write(res);
    res.end();
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'export_error' });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Servidor escuchando en http://localhost:${PORT}`);
});
