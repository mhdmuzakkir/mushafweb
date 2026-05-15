# Daily Report System

Daily HTML reports for Mushaf Task Manager - uploaded automatically from Google Apps Script.

## 📁 Folder Structure

```
daily-report/
├── index.html          # Report viewer with date navigation
├── api/
│   └── receive.php     # Endpoint to receive reports from GAS
├── docs/
│   └── README.md       # This file
└── (no other files)    # Reports are stored in /reports/
```

## 🌐 URLs

| URL | Description |
|-----|-------------|
| `/daily-report/` | Main viewer (loads today's report) |
| `/daily-report/?date=2026-04-11` | View specific date |
| `/daily-report/api/receive.php` | GAS upload endpoint |

## ⏰ Timezone

All reports use **Saudi Arabia Time (AST, UTC+3)**
- No daylight saving time
- Reports generate at 11:59 PM Saudi time
- "Today" is based on Saudi date

## 🔐 Security

The API endpoint requires a secret key. See Google Apps Script configuration for the key.

## 📝 Report Storage

Reports are saved to: `/reports/YYYY-MM-DD.html`

Example:
- `/reports/2026-04-11.html`
- `/reports/2026-04-10.html`

## 🚀 Manual Testing

To test the upload endpoint:

```bash
curl -X POST https://mushaf.linuxproguru.com/daily-report/api/receive.php \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "YOUR_API_KEY",
    "date": "2026-04-11",
    "filename": "2026-04-11.html",
    "html": "<html><body>Test Report</body></html>"
  }'
```

## 🔧 Troubleshooting

### 404 Errors
- Ensure `/reports/` directory exists
- Check that report files were uploaded successfully

### Upload Failures
- Verify API key matches between GAS and PHP
- Check PHP error logs
- Ensure FTP credentials are correct (in receive.php)

### Timezone Issues
- Viewer displays current Saudi time in header
- All dates are relative to Saudi Arabia (AST)
