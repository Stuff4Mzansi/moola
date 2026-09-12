$ErrorActionPreference = 'Stop'
python scripts/spec_guard.py install
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to install the spec guard.'
}
