<x-mail::message>
# Notificación de Documento Rechazado

Hola,

Te informamos que el documento médico enviado recientemente ha sido **rechazado** tras su evaluación.

<x-mail::button :url="config('app.url')">
Ir al Sistema
</x-mail::button>

Si tienes alguna duda, comunícate con el área de Bienestar Social o Recursos Humanos.

Atentamente,<br>
{{ config('app.name') }}
</x-mail::message>
