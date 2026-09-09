// Extracts plain text from PDF/PPTX files in chatbot-sources/ into chatbot-sources/extracted/*.txt
// Re-run manually whenever a source file is added or updated.
const fs = require('fs');
const path = require('path');
const pdf = require('pdf-parse');

const SOURCE_DIR = path.join(__dirname, 'chatbot-sources');
const OUTPUT_DIR = path.join(SOURCE_DIR, 'extracted');

async function extractPdf(filePath) {
  const buffer = fs.readFileSync(filePath);
  const data = await pdf(buffer);
  return data.text;
}

async function main() {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const files = fs.readdirSync(SOURCE_DIR).filter(f => {
    const full = path.join(SOURCE_DIR, f);
    return fs.statSync(full).isFile();
  });

  if (files.length === 0) {
    console.log('No source files found in chatbot-sources/.');
    return;
  }

  for (const file of files) {
    const ext = path.extname(file).toLowerCase();
    const fullPath = path.join(SOURCE_DIR, file);
    const outName = path.basename(file, ext) + '.txt';
    const outPath = path.join(OUTPUT_DIR, outName);

    if (ext === '.pdf') {
      console.log(`Extracting: ${file}`);
      const text = await extractPdf(fullPath);
      fs.writeFileSync(outPath, text, 'utf8');
      console.log(`  -> ${outName} (${text.length} chars)`);
    } else {
      console.log(`Skipping unsupported file type: ${file}`);
    }
  }

  console.log('Done.');
}

main().catch(err => {
  console.error('Extraction failed:', err);
  process.exit(1);
});
