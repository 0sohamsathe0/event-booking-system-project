param(
    [ValidateRange(1, 65535)]
    [int] $Port = 8000,

    [string] $PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP was not found at: $PhpPath"
}

# Process variables override the ignored .env only for this local server.
# The cloud database, Cloudinary, and Razorpay values still come from .env.
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = "http://127.0.0.1:$Port"
$env:APP_BASE_PATH = ''
$env:SESSION_DRIVER = 'file'
$env:LOG_CHANNEL = 'file'

& $PhpPath -S "127.0.0.1:$Port" -t public public\index.php
