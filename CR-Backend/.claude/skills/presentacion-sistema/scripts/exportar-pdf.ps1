# Exporta el .pptx a PDF con el PowerPoint instalado, para revisar cada
# diapositiva como imagen (pdftoppm). En esta máquina no hay LibreOffice.
#
#   powershell -File exportar-pdf.ps1 -Pptx "C:\ruta\deck.pptx" -Pdf "C:\ruta\deck.pdf"
param([string]$Pptx, [string]$Pdf)
$pp = New-Object -ComObject PowerPoint.Application
$pres = $pp.Presentations.Open($Pptx, $true, $false, $false)
$pres.SaveAs($Pdf, 32)
"diapositivas: " + $pres.Slides.Count
$pres.Close()
$pp.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($pp) | Out-Null
