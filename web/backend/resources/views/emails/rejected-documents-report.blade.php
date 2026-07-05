<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Rechazados</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Roboto,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #047857, #065f46); padding:28px 32px; text-align:center;">
                            <h1 style="color:#ffffff; margin:0; font-size:22px; font-weight:700;">⚠️ Reporte de Documentos Rechazados</h1>
                            <p style="color:#a7f3d0; margin:8px 0 0; font-size:14px;">{{ now()->format('d/m/Y') }} — AgroCalera APPs</p>
                        </td>
                    </tr>

                    <!-- Summary -->
                    <tr>
                        <td style="padding:24px 32px 8px;">
                            <p style="margin:0; font-size:15px; color:#374151;">
                                Se encontraron <strong style="color:#B42318;">{{ $documents->count() }}</strong> documento(s) rechazado(s) durante el día de hoy.
                            </p>
                        </td>
                    </tr>

                    <!-- Table -->
                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <thead>
                                    <tr style="background-color:#f9fafb;">
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">N°</th>
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">Tipo</th>
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">Trabajador</th>
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">DNI</th>
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">Registrado por</th>
                                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280; font-weight:600;">Rechazado por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $doc)
                                    <tr style="border-bottom:1px solid #f3f4f6;">
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->id }}</td>
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->type?->name ?? '-' }}</td>
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->worker?->first_name }} {{ $doc->worker?->last_name }}</td>
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->worker?->dni ?? '-' }}</td>
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->creator?->name ?? '-' }}</td>
                                        <td style="padding:10px 12px; color:#111827;">{{ $doc->statusChangedBy?->name ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f9fafb; padding:20px 32px; text-align:center; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">
                                Este es un correo automático generado por AgroCalera APPs.<br>
                                Por favor no responda a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
