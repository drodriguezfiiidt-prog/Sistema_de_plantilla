param(
    [string] $BaseUrl = 'http://127.0.0.1:8001'
)

# Comprobacion HTTP del servidor PHP en ejecucion (sin dependencias externas).
# Solo se usan literales ASCII para que funcione con cualquier codificacion de consola.
$script:failures = 0

function Check([string] $label, [bool] $condition, [string] $detail = '') {
    if ($condition) {
        Write-Output ('  OK   ' + $label)
        return
    }
    Write-Output ('  FAIL ' + $label + ' ' + $detail)
    $script:failures++
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

Write-Output '1. Rutas publicas'
$homePage = Invoke-WebRequest -Uri ($BaseUrl + '/') -WebSession $session -UseBasicParsing
Check 'GET /' ($homePage.StatusCode -eq 200) ('status ' + $homePage.StatusCode)
Check 'Formulario visible' ($homePage.Content -like '*Asistente de diagn*')

$css = Invoke-WebRequest -Uri ($BaseUrl + '/assets/css/app.css') -WebSession $session -UseBasicParsing
Check 'CSS servido' ($css.RawContentLength -gt 8000) ('bytes ' + $css.RawContentLength)
$js = Invoke-WebRequest -Uri ($BaseUrl + '/assets/js/app.js') -WebSession $session -UseBasicParsing
Check 'JS servido' ($js.RawContentLength -gt 7000) ('bytes ' + $js.RawContentLength)

Write-Output '2. API de contexto'
$context = Invoke-WebRequest -Uri ($BaseUrl + '/api/contexto/?serial=V10-000123') -WebSession $session -UseBasicParsing
$payload = $context.Content | ConvertFrom-Json
Check 'Familia autodetectada' ($payload.family.name -eq 'Bio500/V10') ('familia ' + $payload.family.name)
Check 'Cinco repuestos compatibles' (@($payload.items).Count -eq 5) ('items ' + @($payload.items).Count)

Write-Output '3. Vista previa'
$previewUrl = $BaseUrl + '/vista-previa/?ticket_number=35212348&serial_number=V10-000123&items%5B%5D=1&items%5B%5D=3&warranty_status=Vencida'
$preview = Invoke-WebRequest -Uri $previewUrl -WebSession $session -UseBasicParsing
Check 'Vista previa con documento' ($preview.Content -like '*Total referencial*')
Check 'Tabla tarifaria en HTML' ($preview.Content -like '*Costo referencial*')
Check 'Badge de validacion' ($preview.Content -like '*badge-*')

Write-Output '4. Descarga Word'
$token = [regex]::Match($homePage.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
Check 'Token CSRF presente' ($token.Length -gt 20)

$payload = '_token=' + [uri]::EscapeDataString($token) +
    '&ticket_number=35212348&serial_number=V10-000123&family=&warranty_status=Vencida' +
    '&technical_report=Se+reviso+el+lector+biometrico&items%5B%5D=1&items%5B%5D=3'

$docx = Invoke-WebRequest -Uri ($BaseUrl + '/descargar/') -Method POST -Body $payload `
    -ContentType 'application/x-www-form-urlencoded' -WebSession $session -UseBasicParsing
Check 'DOCX descargado' ($docx.Headers['Content-Type'] -like '*wordprocessingml*') ('tipo ' + $docx.Headers['Content-Type'])
Check 'Nombre del archivo' ($docx.Headers['Content-Disposition'] -like '*Diagnostico_35212348.docx*') ('cabecera ' + $docx.Headers['Content-Disposition'])
Check 'Tamano del docx' ($docx.RawContentLength -gt 1000) ('bytes ' + $docx.RawContentLength)

Write-Output '5. Historial y gestion'
$history = Invoke-WebRequest -Uri ($BaseUrl + '/historial/') -WebSession $session -UseBasicParsing
Check 'Historial accesible' ($history.StatusCode -eq 200)

$guard = Invoke-WebRequest -Uri ($BaseUrl + '/gestion/') -WebSession $session -UseBasicParsing
Check 'Gestion protegida sin sesion' ($guard.Content -like '*Iniciar sesi*' -and $guard.Content -notlike '*de repuestos y precios*')

$loginPage = Invoke-WebRequest -Uri ($BaseUrl + '/login/?next=/gestion/') -WebSession $session -UseBasicParsing
$loginToken = [regex]::Match($loginPage.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
$panel = Invoke-WebRequest -Uri ($BaseUrl + '/login/') -Method POST -Body @{
    _token   = $loginToken
    username = 'admin'
    password = 'GestioBio2026*'
    next     = '/gestion/'
} -WebSession $session -UseBasicParsing
Check 'Login del staff' ($panel.Content -like '*de repuestos y precios*')
Check 'Catalogo en el panel' ($panel.Content -like '*Lector biom*trico*')

Write-Output ''
if ($script:failures -eq 0) {
    Write-Output 'HTTP OK: todas las comprobaciones pasaron.'
    exit 0
}

Write-Output ($script:failures.ToString() + ' fallo(s).')
exit 1

