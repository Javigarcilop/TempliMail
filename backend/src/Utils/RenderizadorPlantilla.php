<?php

declare(strict_types=1);

namespace TempliMail\Utils;

/**
 * Sustituye variables {{nombre}} en asuntos y cuerpos de correo.
 *
 *   {{nombre}}            -> valor del contacto (vacio si no lo tiene)
 *   {{nombre|amigo}}      -> valor por defecto si el contacto no lo tiene
 *   {{enlace_baja}}       -> enlace de baja del contacto
 *
 * Las variables desconocidas se dejan tal cual, para que el error sea visible.
 */
class RenderizadorPlantilla
{
    /** Variables disponibles (para mostrarlas en el frontend). */
    public const VARIABLES = [
        'nombre'      => 'Nombre',
        'apellidos'       => 'Apellidos',
        'nombre_completo'       => 'Nombre completo',
        'correo'           => 'Correo',
        'empresa'         => 'Empresa',
        'cargo'        => 'Cargo',
        'enlace_baja' => 'Enlace de baja',
    ];

    /**
     * Nombres antiguos (en ingles) de las variables, por compatibilidad con
     * plantillas ya guardadas antes de traducir el proyecto al espanol.
     */
    private const ALIAS = [
        'first_name' => 'nombre',
        'last_name' => 'apellidos',
        'full_name' => 'nombre_completo',
        'email' => 'correo',
        'company' => 'empresa',
        'position' => 'cargo',
        'unsubscribe_url' => 'enlace_baja',
    ];

    /** Datos de ejemplo para vistas previas y correos de prueba. */
    public const SAMPLE = [
        'nombre' => 'Ana',
        'apellidos'  => 'García',
        'empresa'    => 'Empresa Ejemplo',
        'cargo'   => 'Directora de Marketing',
        'correo'      => 'ana.garcia@ejemplo.com',
    ];

    /**
     * @param array<string,?string> $vars
     * @param bool $html true = escapa los valores para insertarlos en HTML
     */
    public static function render(string $text, array $vars, bool $html): string
    {
        $vars = self::withDerived($vars);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*(?:\|([^}]*))?\}\}/i',
            function (array $m) use ($vars, $html): string {
                $key = strtolower($m[1]);
                $key = self::ALIAS[$key] ?? $key;

                if (!array_key_exists($key, self::VARIABLES)) {
                    return $m[0];
                }

                $value = trim((string) ($vars[$key] ?? ''));

                if ($value === '') {
                    $value = trim($m[2] ?? '');
                }

                return $html ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
            },
            $text
        );
    }

    /** Elimina saltos de linea de un asunto (evita inyeccion de cabeceras). */
    public static function sanitizeSubject(string $asunto): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $asunto));
    }

    private static function withDerived(array $vars): array
    {
        $vars['nombre_completo'] = trim(
            (string) ($vars['nombre'] ?? '') . ' ' . (string) ($vars['apellidos'] ?? '')
        );

        return $vars;
    }
}
