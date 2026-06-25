<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redefinir senha - {{ $brandName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;margin:0;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:32px 32px 20px;">
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="150" style="display:block;max-width:150px;height:auto;margin:0 auto 18px;">
                            <h1 style="margin:0;color:#111827;font-size:24px;line-height:1.3;font-weight:700;">Redefinir senha</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;color:#4b5563;font-size:16px;line-height:1.6;">
                            <p style="margin:0 0 16px;">Olá!</p>
                            <p style="margin:0 0 24px;">Recebemos uma solicitação para redefinir a senha da sua conta na {{ $brandName }}.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" bgcolor="#111827" style="border-radius:6px;">
                                        <a href="{{ $resetUrl }}" style="display:inline-block;padding:13px 24px;color:#ffffff;background:#111827;border-radius:6px;text-decoration:none;font-size:15px;font-weight:700;">
                                            Redefinir senha
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 12px;">Este link expira em {{ $expirationMinutes }} minutos.</p>
                            <p style="margin:0 0 24px;">Se você não solicitou a redefinição, nenhuma ação é necessária.</p>
                            <hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0;">
                            <p style="margin:0 0 10px;color:#6b7280;font-size:13px;line-height:1.5;">Se o botão não funcionar, copie e cole este link no navegador:</p>
                            <p style="margin:0;word-break:break-all;font-size:13px;line-height:1.5;">
                                <a href="{{ $resetUrl }}" style="color:#2563eb;text-decoration:underline;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 32px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            © {{ date('Y') }} {{ $brandName }}. Todos os direitos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
