<?php

$exercise = (string) env('AEAT_FISCAL_EXERCISE', '2025');
$exerciseSuffix = substr($exercise, -2);
$defaultReleaseDate = match ($exercise) {
    '2025' => '2026-03-19',
    default => null,
};

return [
    'exercise' => $exercise,

    'release_date' => env('AEAT_FISCAL_RELEASE_DATE', $defaultReleaseDate),

    'timeouts' => [
        'connect' => (int) env('AEAT_HTTP_CONNECT_TIMEOUT', 10),
        'request' => (int) env('AEAT_HTTP_TIMEOUT', 30),
    ],

    'layout' => [
        'json_path' => env('AEAT_LAYOUT_JSON_PATH', resource_path('aeat/layouts/renta_'.$exercise.'.json')),
    ],

    'urls' => [
        'ratification_check' => env('AEAT_RATIFICATION_CHECK_URL', 'https://www1.agenciatributaria.gob.es/wlpl/TOAG-JDIT/IsRatificadoExJson'),
        'ratification' => [
            'certificate' => env('AEAT_RATIFICATION_CERTIFICATE_URL', 'https://www1.agenciatributaria.gob.es/wlpl/TOAG-JDIT/RatIdentificacion?COD_SERVICIO=MDAPL'),
            'reference' => env('AEAT_RATIFICATION_REFERENCE_URL', 'https://www9.agenciatributaria.gob.es/wlpl/TOAG-JDIT/RatIdentificacion?COD_SERVICIO=MDAPL'),
            'clave_movil' => env('AEAT_RATIFICATION_CLAVE_URL', 'https://www6.agenciatributaria.gob.es/wlpl/TOAG-JDIT/RatIdentificacion?COD_SERVICIO=MDAPL'),
            'collaborator_social' => env('AEAT_RATIFICATION_COLLABORATOR_URL', 'https://www1.agenciatributaria.gob.es/wlpl/TOAG-JDIT/RatIdentificacionCS?COD_SERVICIO=MDAPL'),
        ],
        'certificate_download' => env('AEAT_CERTIFICATE_DOWNLOAD_URL', 'https://www1.agenciatributaria.gob.es/wlpl/DFPA-D182/SvDesDF'.$exerciseSuffix.'Pei'),
        'reference_auth' => env('AEAT_REFERENCE_AUTH_URL', 'https://www2.agenciatributaria.gob.es/wlpl/DABJ-REN0/ValidacionReferenciaServlet'),
        'reference_download' => env('AEAT_REFERENCE_DOWNLOAD_URL', 'https://www9.agenciatributaria.gob.es/wlpl/DFPA-D182/SvDesDF'.$exerciseSuffix.'Pei'),
        'clave_movil' => [
            'authenticate' => env('AEAT_CLAVE_AUTH_URL', 'https://www2.agenciatributaria.gob.es/wlpl/BUCV-JDIT/AutenticaDniNieContrasteh'),
            'request_sms' => env('AEAT_CLAVE_REQUEST_SMS_URL', 'https://www12.agenciatributaria.gob.es/wlpl/MOVI-P24H/3RD/ObtenerClaveMovilSMS'),
            'validate_pin' => env('AEAT_CLAVE_VALIDATE_PIN_URL', 'https://www12.agenciatributaria.gob.es/wlpl/MOVI-P24H/3RD/ValidarClaveMovilSMS'),
            'download' => env('AEAT_CLAVE_DOWNLOAD_URL', 'https://www6.agenciatributaria.gob.es/wlpl/DFPA-D182/SvDesDF'.$exerciseSuffix.'Pei'),
            'ref_path' => env('AEAT_CLAVE_REF_PATH', '/wlpl/MOVI-P24H/3RD/ObtenerClaveMovilSMS'),
        ],
    ],

    'download_error_messages' => [
        'Es obligatorio el Nif del Contribuyente para su identificacion.',
        'Error en Parametro de entrada: Es obligatorio indicar el parametro de peticion de datos personales.',
        'Existen problemas en la Identificacion del Contribuyente.',
        'No se ha podido descargar el archivo de Datos Fiscales del NIF.',
        'No esta autorizado a este Tramite.',
        'Error de Seguridad.',
        'Por razones tecnicas, no es posible atender su peticion de datos fiscales a traves de este servicio.',
        'Error 5001 Contribuyente no ha ratificado su domicilio fiscal.',
    ],
];