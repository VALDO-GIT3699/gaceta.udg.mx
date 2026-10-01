<#
.SYNOPSIS
    Genera el inventario de archivos de /wp-content/uploads de Gaceta.

.DESCRIPTION
    Recorre el árbol de uploads y escribe un archivo de TEXTO con la ruta
    relativa y el tamaño de cada archivo.

    NO copia, NO mueve, NO modifica y NO abre el contenido de ningún archivo.
    Sólo lee los metadatos del directorio. Es seguro ejecutarlo sobre 82 GB.

    El resultado pesa unos pocos MB aunque el árbol pese 82 GB, porque sólo
    contiene nombres y tamaños. Ese archivo es lo único que hay que enviar.

    PARA QUÉ SIRVE
    La base de datos de WordPress referencia 48 358 archivos. Con este
    inventario se determina, sin mover un solo byte:

      - cuáles de esas 48 358 referencias existen de verdad
      - cuáles faltan, y con qué cobertura por año
      - dónde está cada una de las 16 102 referencias de la columna foto1,
        que sólo guardan el nombre del archivo y no su carpeta (decisión D-17)

    COMPATIBILIDAD
    Funciona en Windows PowerShell 5.1, que es el que viene por defecto en
    Windows 10 y 11.

    Una versión anterior de este script usaba [System.IO.EnumerationOptions],
    que sólo existe en .NET Core y PowerShell 7. En PowerShell 5.1 fallaba con
    "no se encuentra el tipo". Se reescribió con Get-ChildItem, que está en
    todas las versiones. Es algo más lento pero funciona en cualquier equipo.

    La escritura es incremental con un StreamWriter, así que el consumo de
    memoria se mantiene plano aunque haya cientos de miles de archivos.

.PARAMETER Uploads
    Carpeta raíz de uploads: la que contiene las carpetas por año
    (2015, 2016, ...).

.PARAMETER Salida
    Archivo de texto a generar. Por omisión, en el Escritorio.

.EXAMPLE
    .\inventario-uploads.ps1 -Uploads "D:\gaceta\wp-content\uploads"

.EXAMPLE
    .\inventario-uploads.ps1 -Uploads "D:\uploads" -Salida "C:\temp\inv.txt"
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$Uploads,

    [string]$Salida = (Join-Path ([Environment]::GetFolderPath('Desktop')) 'uploads-inventario.txt')
)

if (-not (Test-Path -LiteralPath $Uploads)) {
    Write-Host "No existe la carpeta: $Uploads" -ForegroundColor Red
    exit 1
}

$raiz = (Resolve-Path -LiteralPath $Uploads).Path.TrimEnd('\')
$prefijo = $raiz.Length + 1

Write-Host ""
Write-Host "Inventario de uploads de Gaceta" -ForegroundColor Cyan
Write-Host "-------------------------------"
Write-Host "Carpeta : $raiz"
Write-Host "Salida  : $Salida"
Write-Host ""
Write-Host "No se copia, no se mueve y no se modifica nada." -ForegroundColor Green
Write-Host "Solo se leen nombres y tamanos. Puede tardar varios minutos." -ForegroundColor Green
Write-Host ""

$escritor = New-Object System.IO.StreamWriter(
    $Salida,
    $false,
    (New-Object System.Text.UTF8Encoding($false))
)

$escritor.WriteLine("# Inventario de uploads de Gaceta UDG")
$escritor.WriteLine("# Generado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
$escritor.WriteLine("# Equipo: $env:COMPUTERNAME")
$escritor.WriteLine("# Raiz: $raiz")
$escritor.WriteLine("# Formato: ruta_relativa<TAB>bytes")
$escritor.WriteLine("#")

$n = 0
$bytes = 0L
$cronometro = [System.Diagnostics.Stopwatch]::StartNew()

try {
    # -Force incluye archivos ocultos. -ErrorAction SilentlyContinue continúa
    # si alguna carpeta tiene permisos restringidos: es mejor un inventario
    # con un hueco que ningún inventario.
    Get-ChildItem -LiteralPath $raiz -Recurse -File -Force -ErrorAction SilentlyContinue |
        ForEach-Object {
            $rel = $_.FullName.Substring($prefijo).Replace('\', '/')
            $escritor.WriteLine($rel + "`t" + $_.Length)
            $script:n++
            $script:bytes += $_.Length

            if (($script:n % 20000) -eq 0) {
                Write-Host ("  {0,10:N0} archivos   {1,8:N2} GB   {2,5}s" -f `
                    $script:n,
                    ($script:bytes / 1GB),
                    [math]::Round($cronometro.Elapsed.TotalSeconds))
            }
        }
}
finally {
    $escritor.Flush()
    $escritor.Close()
}

$cronometro.Stop()
$mbSalida = [math]::Round((Get-Item -LiteralPath $Salida).Length / 1MB, 2)

Write-Host ""
Write-Host "LISTO" -ForegroundColor Green
Write-Host "-----"
Write-Host ("Archivos inventariados : {0:N0}" -f $n)
Write-Host ("Tamano total del arbol : {0:N2} GB" -f ($bytes / 1GB))
Write-Host ("Tiempo                 : {0}s" -f [math]::Round($cronometro.Elapsed.TotalSeconds))
Write-Host ""
Write-Host ("ARCHIVO A ENVIAR : {0}" -f $Salida) -ForegroundColor Yellow
Write-Host ("Pesa {0} MB. Es lo unico que hay que mandar." -f $mbSalida) -ForegroundColor Yellow
Write-Host ""

if ($mbSalida -gt 20) {
    Write-Host "Pesa mas de 20 MB. Para comprimirlo:" -ForegroundColor Cyan
    Write-Host ("  Compress-Archive -Path '{0}' -DestinationPath '{0}.zip'" -f $Salida)
    Write-Host ""
}
