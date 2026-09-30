<?php

namespace TempliMail\Services;

use TempliMail\Exceptions\ExcepcionApi;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class ServicioSubidaPlantilla
{
    private const MAX_SIZE = 5242880; // 5MB

    public static function process(array $file): string
    {
        if (!isset($file['tmp_name'], $file['nombre'], $file['size'])) {
            throw new ExcepcionApi('Archivo inválido.');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ExcepcionApi('No se pudo subir el archivo (error ' . $file['error'] . ').');
        }

        if ($file['size'] === 0 || !file_exists($file['tmp_name'])) {
            throw new ExcepcionApi('Archivo vacío o no válido.');
        }

        if ($file['size'] > self::MAX_SIZE) {
            throw new ExcepcionApi('El archivo supera el tamaño máximo permitido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/pdf'
        ];

        if (!in_array($mime, $allowedMimes)) {
            throw new ExcepcionApi('Tipo de archivo no permitido.');
        }

        return match ($mime) {
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                => self::convertDocxToHtml($file['tmp_name']),
            'application/pdf'
                => self::convertPdfToHtml($file['tmp_name']),
        };
    }

    private static function convertDocxToHtml(string $path): string
    {
        $phpWord = IOFactory::load($path, 'Word2007');
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

        ob_start();
        $htmlWriter->save('php://output');
        $html = ob_get_clean();

        $cleanHtml = preg_replace(
            [
                '/<!DOCTYPE.+?>/',
                '/<html.+?>/',
                '/<\/html>/',
                '/<body>|<\/body>/'
            ],
            '',
            $html
        );

        return trim($cleanHtml);
    }

    private static function convertPdfToHtml(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $text = trim($pdf->getText());

        if ($text === '') {
            throw new ExcepcionApi('No se pudo extraer contenido del PDF.');
        }

        return '<p>' . nl2br(htmlentities($text)) . '</p>';
    }
}