<?php

declare(strict_types=1);

namespace TempliMail\Utils;

/**
 * Sustituye variables {{nombre}} en asuntos y cuerpos de correo.
 *
 *   {{first_name}}            -> valor del contacto (vacio si no lo tiene)
 *   {{first_name|amigo}}      -> valor por defecto si el contacto no lo tiene
 *   {{unsubscribe_url}}       -> enlace de baja del contacto
 *
 * Las variables desconocidas se dejan tal cual, para que el error sea visible.
 */
class TemplateRenderer
{
    /** Variables disponibles (para mostrarlas en el frontend). */
    public const VARIABLES = [
        'first_name'      => 'Nombre',
        'last_name'       => 'Apellidos',
        'full_name'       => 'Nombre completo',
        'email'           => 'Email',
        'company'         => 'Empresa',
        'position'        => 'Cargo',
        'unsubscribe_url' => 'Enlace de baja',
    ];

    /** Datos de ejemplo para vistas previas y correos de prueba. */
    public const SAMPLE = [
        'first_name' => 'Ana',
        'last_name'  => 'García',
        'company'    => 'Empresa Ejemplo',
        'position'   => 'Directora de Marketing',
        'email'      => 'ana.garcia@ejemplo.com',
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
    public static function sanitizeSubject(string $subject): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
    }

    private static function withDerived(array $vars): array
    {
        $vars['full_name'] = trim(
            (string) ($vars['first_name'] ?? '') . ' ' . (string) ($vars['last_name'] ?? '')
        );

        return $vars;
    }
}
