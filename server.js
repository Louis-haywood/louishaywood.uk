/**
 * Portfolio CMS Server
 * Run: node server.js
 * Then open: http://yourdomain.com/admin.html
 *
 * Password is set in .env (ADMIN_PASSWORD=...)
 */

require('dotenv').config();

const express = require('express');
const fs      = require('fs');
const path    = require('path');
const multer  = require('multer');

const app  = express();
const PORT = process.env.PORT || 3000;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin'; // set in .env

const ROOT = __dirname;

// ── Middleware ────────────────────────────────────────────────────────────────
app.use(express.json({ limit: '10mb' }));
app.use(express.static(ROOT));

// ── Auth helper ───────────────────────────────────────────────────────────────
function requireAuth(req, res, next) {
  const pw = req.headers['x-admin-password'];
  if (!pw || pw !== ADMIN_PASSWORD) {
    return res.status(401).json({ error: 'Incorrect password.' });
  }
  next();
}

// ── Image upload ──────────────────────────────────────────────────────────────
const storage = multer.diskStorage({
  destination: path.join(ROOT, 'images'),
  filename(req, file, cb) {
    const ext = path.extname(file.originalname).toLowerCase();
    cb(null, `upload-${Date.now()}${ext}`);
  }
});
const upload = multer({
  storage,
  limits: { fileSize: 15 * 1024 * 1024 },
  fileFilter(req, file, cb) {
    const allowed = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif'];
    const ext = path.extname(file.originalname).toLowerCase();
    cb(null, allowed.includes(ext));
  }
});

// ── API routes ────────────────────────────────────────────────────────────────

// GET /api/content  – public (the live site also fetches this)
app.get('/api/content', (req, res) => {
  try {
    const raw = fs.readFileSync(path.join(ROOT, 'content.json'), 'utf8');
    res.json(JSON.parse(raw));
  } catch (e) {
    res.status(500).json({ error: 'Could not read content.json' });
  }
});

// POST /api/content  – save edited content (password required)
app.post('/api/content', requireAuth, (req, res) => {
  try {
    const json = JSON.stringify(req.body, null, 2);
    fs.writeFileSync(path.join(ROOT, 'content.json'), json, 'utf8');
    res.json({ ok: true, message: 'Saved successfully.' });
  } catch (e) {
    res.status(500).json({ error: 'Could not write content.json' });
  }
});

// POST /api/upload  – upload an image (password required)
app.post('/api/upload', requireAuth, upload.single('image'), (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'No image received.' });
  // Return the URL relative to the site root so it works both locally and on the live site
  res.json({ url: `images/${req.file.filename}` });
});

// ── Start ─────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`\n  Portfolio CMS running at  https://louishaywood.uk:${PORT}`);
  console.log(`  Admin panel              https://louishaywood.uk:${PORT}/admin.html`);
  console.log(`  Password                 ${ADMIN_PASSWORD === 'admin' ? 'admin  ← change this!' : '(set via ADMIN_PASSWORD)'}\n`);
});
