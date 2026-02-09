<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class UploadTemplateService
{
    public static function process(array $file): string
    {
        if (!isset($file['tmp_name'], $file['name'], $file['size'])) {
            throw new Exception('Archivo inválido.');
        }

        if ($file['size'] === 0 || !file_exists($file['tmp_name'])) {
            throw new Exception('Archivo vacío o no válido.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['docx', 'pdf'])) {
            throw new Exception("Formato no compatible: $ext");
        }

        return match ($ext) {
            'docx' => self::convertDocxToHtml($file['tmp_name']),
            'pdf'  => self::convertPdfToHtml($file['tmp_name']),
        };
    }

    private static function convertDocxToHtml(string $path): string
    {
        $phpWord = IOFactory::load($path, 'Word2007');
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

        ob_start();
        $htmlWriter->save('php://output');
        $html = ob_get_clean();

        return trim(
            preg_replace(
                [
                    '/<!DOCTYPE.+?>/',
                    '/<html.+?>/',
                    '/<\/html>/',
                    '/<body>|<\/body>/'
                ],
                '',
                $html
            )
        );
    }

    private static function convertPdfToHtml(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $text = trim($pdf->getText());

        if ($text === '') {
            throw new Exception('No se pudo extraer contenido del PDF.');
        }

        return '<p>' . nl2br(htmlentities($text)) . '</p>';
    }
}
