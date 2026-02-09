<?php

require_once __DIR__ . '/../models/MailModel.php';
require_once __DIR__ . '/../utils/Mailer.php';

class MailService
{
    public static function sendSingle(array $data): void
    {
        if (!isset($data['to'], $data['subject'], $data['body'])) {
            throw new Exception('Faltan datos obligatorios');
        }

        Mailer::send($data['to'], $data['subject'], $data['body']);
    }

    public static function sendMassive(array $data): array
    {
        if (empty($data['contactos']) || empty($data['plantilla_id'])) {
            throw new Exception('Datos incompletos');
        }

        $plantilla = MailModel::getTemplateById((int)$data['plantilla_id']);

        if (!$plantilla) {
            throw new Exception('Plantilla no encontrada');
        }

        $programado = !empty($data['fecha_programada']);
        $estado     = $programado ? 'pendiente' : 'enviado';
        $fechaEnvio = $programado ? null : date('Y-m-d H:i:s');

        $envioId = MailModel::createEnvio(
            (int)$data['plantilla_id'],
            $plantilla['asunto'],
            $plantilla['contenido_html'],
            $estado,
            $data['fecha_programada'] ?? null,
            $fechaEnvio
        );

        $contactos = MailModel::getContactsByIds($data['contactos']);

        foreach ($contactos as $c) {
            MailModel::linkEnvioContacto($envioId, (int)$c['id']);

            if (!$programado) {
                Mailer::send($c['email'], $plantilla['asunto'], $plantilla['contenido_html']);
            }
        }

        return ['programado' => $programado];
    }

    public static function historial(): array
    {
        return MailModel::getHistorial();
    }

    public static function ejecutarProgramados(): int
    {
        $envios = MailModel::getPendientes();

        foreach ($envios as $envio) {
            $destinatarios = MailModel::getDestinatarios((int)$envio['id']);

            foreach ($destinatarios as $d) {
                Mailer::send($d['email'], $envio['asunto'], $envio['mensaje']);
            }

            MailModel::marcarEnviado((int)$envio['id']);
        }

        return count($envios);
    }
}
