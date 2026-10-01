<#
.SYNOPSIS
    Genera el inventario de archivos de /wp-content/uploads de Gaceta.

.DESCRIPTION
    Recorre el árbol de uploads y escribe un archivo de TEXTO con la ruta
    relativa y el tamaño de cada archivo.

    NO copia, NO mueve, NO modifica y NO abre ningún archivo. Sólo lee los
    metadatos del directorio. Es seguro ejecutarlo sobre los 82 GB.

    El resultado pesa unos pocos MB aunque el árbol pese 82 GB, porque sólo
    contiene nombres y tamaños. Ese archivo es lo único que hay que enviar.

    PARA QUÉ SIRVE
    La base de datos de WordPress referencia 48 358 archivos. Con este
    inventario se puede determinar, sin mover un solo byte:

      - cuáles de esas 48 358 referencias existen de verdad
      - cuáles faltan
      - dónde está cada una de las 16 102 referencias de la columna foto1,
        que sólo guardan el nombre del archivo y no su carpeta (decisión D-17)

    RENDIMIENTO
    Usa la enumeración de .NET en lugar de Get-ChildItem, que es mucho más
    lenta en árboles de cientos de miles de archivos. Escribe de forma
    incremental, así que el consumo de memoria es plano aunque haya millones
    de archivos.

.PARAMETER Uploads
    Carpeta raíz de uploads. Es la que contiene las carpetas por año
    (2015, 2016, ...).

.PARAMETER Salida
    Archivo de texto a generar. Por omisión, uploads-inventario.txt junto al
    script.

.EXAMPLE
    .\inventario-uploads.ps1 -Uploads "D:\gaceta\wp-content\uploads"

.EXAMPLE
    .\inventario-uploads.ps1 -Uploads "D:\uploads" -Salida "C:\temp\inv.txt"
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$Uploads,

    [string]$Salida = (Join-Path $PSScriptRoot "uploads-inventario.txt")
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $Uploads)) {
    Write-Error "No existe la carpeta: $Uploads"
    exit 1
}

$raiz = (Resolve-Path -LiteralPath $Uploads).Path.TrimEnd('\')
$prefijo = $raiz.Length + 1

Write-Host ""
Write-Host "Inventario de uploads de Gaceta" -ForegroundColor Cyan
Write-Host "--------------------------------"
Write-Host "Carpeta : $raiz"
Write-Host "Salida  : $Salida"
Write-Host ""
Write-Host "No se copia, no se mueve y no se modifica nada." -ForegroundColor Green
Write-Host "Sólo se leen nombres y tamanos." -ForegroundColor Green
Write-Host ""

$opciones = [System.IO.EnumerationOptions]::new()
$opciones.RecurseSubdirectories = $true
# Un árbol de uploads puede tener carpetas con permisos raros o enlaces.
# Continuar en lugar de abortar: es mejor un inventario con un aviso que
# ningún inventario.
$opciones.IgnoreInaccessible = $true
$opciones.AttributesToSkip = [System.IO.FileAttributes]::ReparsePoint

$escritor = [System.IO.StreamWriter]::new(
    $Salida,
    $false,
    [System.Text.UTF8Encoding]::new($false)
)

# Cabecera: deja constancia de qué se inventarió y cuándo.
$escritor.WriteLine("# Inventario de uploads de Gaceta UDG")
$escritor.WriteLine("# Generado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
$escritor.WriteLine("# Equipo: $env:COMPUTERNAME")
$escritor.WriteLine("# Raiz: $raiz")
$escritor.WriteLine("# Formato: ruta_relativa<TAB>bytes")
$escritor.WriteLine("#")

$n = 0
$bytes = 0L
$errores = 0
$cronometro = [System.Diagnostics.Stopwatch]::StartNew()

try {
    foreach ($ruta in [System.IO.Directory]::EnumerateFiles($raiz, '*', $opciones)) {
        try {
            $info = [System.IO.FileInfo]::new($ruta)
            # Ruta relativa con barras hacia adelante, igual que las guarda
            # WordPress en _wp_attached_file. Así se puede cruzar directamente.
            $rel = $ruta.Substring($prefijo).Replace('\', '/')
            $escritor.WriteLine("$rel`t$($info.Length)")
            $n++
            $bytes += $info.Length
        }
        catch {
            $errores++
        }

        if (($n % 20000) -eq 0) {
            $gb = [math]::Round($bytes / 1GB, 2)
            Write-Host ("  {0,10:N0} archivos   {1,8} GB   {2,5}s" -f `
                $n, $gb, [math]::Round($cronometro.Elapsed.TotalSeconds))
        }
    }
}
finally {
    $escritor.Flush()
    $escritor.Close()
}

$cronometro.Stop()
$gbTotal = [math]::Round($bytes / 1GB, 2)
$mbSalida = [math]::Round((Get-Item -LiteralPath $Salida).Length / 1MB, 2)

Write-Host ""
Write-Host "LISTO" -ForegroundColor Green
Write-Host "-----"
Write-Host ("Archivos inventariados : {0:N0}" -f $n)
Write-Host ("Tamano total del arbol : {0} GB" -f $gbTotal)
Write-Host ("Archivos no accesibles : {0}" -f $errores)
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

if ($errores -gt 0) {
    Write-Host ("AVISO: {0} archivos no se pudieron leer (permisos o enlaces)." -f $errores) -ForegroundColor Yellow
    Write-Host "El inventario esta completo salvo por esos. No es un fallo del script."
    Write-Host ""
}
