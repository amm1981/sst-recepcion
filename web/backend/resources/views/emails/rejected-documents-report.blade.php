<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de documentos rechazados</title>
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff; padding:24px 0;">
        <tr>
            <td align="center" style="padding:0 16px;">
                <table width="680" cellpadding="0" cellspacing="0" role="presentation" style="width:100%; max-width:680px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px 22px; border-bottom:1px solid #e5e7eb;">
                            <img src="{{ rtrim(env('FRONTEND_URL', config('app.url')), '/') }}/logotipo_docssalud.png" alt="DocsSalud" style="display:block; height:58px; max-width:220px; object-fit:contain; margin:0 0 16px;">
                            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#111827;">Reporte diario de documentos rechazados</h1>
                            <p style="margin:8px 0 0; color:#6b7280; font-size:14px;">{{ now()->format('d/m/Y') }}</p>
                            @if($isTest)
                                <div style="display:inline-block; margin-top:14px; padding:6px 10px; border-radius:6px; background:#ecfdf5; color:#047857; font-size:12px; font-weight:700;">Correo de prueba</div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #fee2e2; border-radius:8px; background:#fff7f7;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:13px; color:#991b1b; font-weight:700; text-transform:uppercase;">Total rechazados del dia</div>
                                        <div style="font-size:36px; line-height:1; font-weight:800; color:#b91c1c; margin-top:8px;">{{ $documents->count() }}</div>
                                        <p style="margin:10px 0 0; color:#7f1d1d; font-size:14px;">Los documentos listados requieren revision o correccion por el equipo responsable.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse; font-size:13px;">
                                <thead>
                                    <tr>
                                        <th style="padding:11px 10px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280;">Documento</th>
                                        <th style="padding:11px 10px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280;">Tipo</th>
                                        <th style="padding:11px 10px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280;">Trabajador</th>
                                        <th style="padding:11px 10px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280;">DNI</th>
                                        <th style="padding:11px 10px; text-align:left; border-bottom:2px solid #e5e7eb; color:#6b7280;">Rechazado por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $doc)
                                        <tr>
                                            <td style="padding:12px 10px; border-bottom:1px solid #f3f4f6; color:#111827; font-weight:700;">#{{ $doc->id }}</td>
                                            <td style="padding:12px 10px; border-bottom:1px solid #f3f4f6; color:#374151;">{{ $doc->type?->name ?? '-' }}</td>
                                            <td style="padding:12px 10px; border-bottom:1px solid #f3f4f6; color:#374151;">{{ trim(($doc->worker?->first_name ?? '') . ' ' . ($doc->worker?->last_name ?? '')) ?: '-' }}</td>
                                            <td style="padding:12px 10px; border-bottom:1px solid #f3f4f6; color:#374151;">{{ $doc->worker?->dni ?? '-' }}</td>
                                            <td style="padding:12px 10px; border-bottom:1px solid #f3f4f6; color:#374151;">{{ $doc->statusChangedBy?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:12px; line-height:1.5;">
                            Este correo se genera automaticamente cuando existen documentos rechazados durante el dia.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
