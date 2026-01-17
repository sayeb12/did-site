<?php
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);

$fontPath = __DIR__ . '/fonts/NotoSansBengali-Regular.ttf';
$fontData = base64_encode(file_get_contents($fontPath));
$fontFace = "@font-face{ font-family:'NotoSansBengali'; src:url('data:font/ttf;base64,$fontData') format('truetype'); }";
$css = $fontFace . ' body{ font-family: "NotoSansBengali", sans-serif; font-size:14px; }';

$html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';
$html .= '<p>বাংলা লেখা পরীক্ষা — ২ জানুয়ারি - শুভ জন্মদিন</p>';
$html .= '</body></html>';

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();
$dompdf->stream('test_bengali.pdf', ["Attachment" => false]);
