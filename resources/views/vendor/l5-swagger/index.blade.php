<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentationTitle }}</title>
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-16x16.png') }}" sizes="16x16"/>
    <style>
    html
    {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
    }
    *,
    *:before,
    *:after
    {
        box-sizing: inherit;
    }

    body {
      margin:0;
      background: #fafafa;
    }
    </style>
    @if(config('l5-swagger.defaults.ui.display.dark_mode'))
        <style>
            body#dark-mode,
            #dark-mode .scheme-container {
                background: #1b1b1b;
            }
            #dark-mode .scheme-container,
            #dark-mode .opblock .opblock-section-header{
                box-shadow: 0 1px 2px 0 rgba(255, 255, 255, 0.15);
            }
            #dark-mode .operation-filter-input,
            #dark-mode .dialog-ux .modal-ux,
            #dark-mode input[type=email],
            #dark-mode input[type=file],
            #dark-mode input[type=password],
            #dark-mode input[type=search],
            #dark-mode input[type=text],
            #dark-mode textarea{
                background: #343434;
                color: #e7e7e7;
            }
            #dark-mode .title,
            #dark-mode li,
            #dark-mode p,
            #dark-mode table,
            #dark-mode label,
            #dark-mode .opblock-tag,
            #dark-mode .opblock .opblock-summary-operation-id,
            #dark-mode .opblock .opblock-summary-path,
            #dark-mode .opblock .opblock-summary-path__deprecated,
            #dark-mode h1,
            #dark-mode h2,
            #dark-mode h3,
            #dark-mode h4,
            #dark-mode h5,
            #dark-mode .btn,
            #dark-mode .tab li,
            #dark-mode .parameter__name,
            #dark-mode .parameter__type,
            #dark-mode .prop-format,
            #dark-mode .loading-container .loading:after{
                color: #e7e7e7;
            }
            #dark-mode .opblock-description-wrapper p,
            #dark-mode .opblock-external-docs-wrapper p,
            #dark-mode .opblock-title_normal p,
            #dark-mode .response-col_status,
            #dark-mode table thead tr td,
            #dark-mode table thead tr th,
            #dark-mode .response-col_links,
            #dark-mode .swagger-ui{
                color: wheat;
            }
            #dark-mode .parameter__extension,
            #dark-mode .parameter__in,
            #dark-mode .model-title{
                color: #949494;
            }
            #dark-mode table thead tr td,
            #dark-mode table thead tr th{
                border-color: rgba(120,120,120,.2);
            }
            #dark-mode .opblock .opblock-section-header{
                background: transparent;
            }
            #dark-mode .opblock.opblock-post{
                background: rgba(73,204,144,.25);
            }
            #dark-mode .opblock.opblock-get{
                background: rgba(97,175,254,.25);
            }
            #dark-mode .opblock.opblock-put{
                background: rgba(252,161,48,.25);
            }
            #dark-mode .opblock.opblock-delete{
                background: rgba(249,62,62,.25);
            }
            #dark-mode .loading-container .loading:before{
                border-color: rgba(255,255,255,10%);
                border-top-color: rgba(255,255,255,.6);
            }
            #dark-mode svg:not(:root){
                fill: #e7e7e7;
            }
            #dark-mode .opblock-summary-description {
                color: #fafafa;
            }
        </style>
    @endif
    <style>
        /* Identidad visual: azul oscuro, blanco y acento turquesa. */
        :root { --navy: #0b1f3a; --navy-2: #13315c; --teal: #14b8a6; --teal-dark: #0f8f82; --paper: #ffffff; --ink: #1f2a37; }
        body { background: #f4f7fb; color: var(--ink); }
        .swagger-ui .topbar { background: var(--navy); border-bottom: 3px solid var(--teal); padding: 12px 0; }
        .swagger-ui .topbar .download-url-wrapper .select-label { color: #e6fffb; }
        .swagger-ui .topbar .download-url-wrapper input[type=text] { border-color: var(--teal); }
        .swagger-ui .info { margin: 32px 0 24px; padding: 24px 28px; background: var(--paper); border-left: 5px solid var(--teal); border-radius: 8px; box-shadow: 0 1px 3px rgba(11, 31, 58, .08); }
        .swagger-ui .info .title { color: var(--navy); }
        .swagger-ui .info .title small.version-stamp { background: var(--teal); }
        .swagger-ui .info a, .swagger-ui a.nostyle { color: var(--teal-dark); }
        .swagger-ui .info table { border-collapse: collapse; margin: 8px 0; }
        .swagger-ui .info table td, .swagger-ui .info table th { border: 1px solid #d8e1ec; padding: 6px 10px; }
        .swagger-ui .scheme-container { background: var(--paper); box-shadow: 0 1px 3px rgba(11, 31, 58, .08); border-radius: 8px; }
        .swagger-ui .btn.authorize { color: var(--teal-dark); border-color: var(--teal); }
        .swagger-ui .btn.authorize svg { fill: var(--teal-dark); }
        .swagger-ui .btn.execute { background: var(--navy-2); border-color: var(--navy-2); }
        .swagger-ui .opblock-tag { color: var(--navy); border-bottom-color: #d8e1ec; }
        .swagger-ui .opblock .opblock-summary-description { color: var(--ink); }
        .swagger-ui .opblock.opblock-get .opblock-summary-method { background: var(--teal-dark); }
        .swagger-ui .opblock.opblock-get { border-color: var(--teal); background: rgba(20, 184, 166, .06); }
        .swagger-ui .opblock.opblock-post .opblock-summary-method { background: var(--navy-2); }
        .swagger-ui .opblock.opblock-post { border-color: var(--navy-2); background: rgba(19, 49, 92, .05); }
        /* Modo oscuro de Swagger UI: misma identidad con contraste suficiente. */
        .dark-mode body, body.dark-mode { background: #0a1628; }
        .dark-mode .swagger-ui .info, .dark-mode .swagger-ui .scheme-container { background: #10243f; box-shadow: none; }
        .dark-mode .swagger-ui .info .title, .dark-mode .swagger-ui .info h1, .dark-mode .swagger-ui .info h2, .dark-mode .swagger-ui .info h3 { color: #ffffff; }
        .dark-mode .swagger-ui .info p, .dark-mode .swagger-ui .info li, .dark-mode .swagger-ui .info td, .dark-mode .swagger-ui .info th, .dark-mode .swagger-ui .info table td, .dark-mode .swagger-ui .renderedMarkdown p { color: #d7e3f1; }
        .dark-mode .swagger-ui .info table td, .dark-mode .swagger-ui .info table th { border-color: #24456f; }
        .dark-mode .swagger-ui .info a, .dark-mode .swagger-ui .opblock-tag { color: #5eead4; }
    </style>
</head>

<body @if(config('l5-swagger.defaults.ui.display.dark_mode')) id="dark-mode" @endif>
<div id="swagger-ui"></div>

<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}"></script>
<script>
    window.onload = function() {
        const urls = [];

        @foreach($urlsToDocs as $title => $url)
            urls.push({name: @json($title), url: @json($url)});
        @endforeach

        // Build a system
        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            urls: urls,
            "urls.primaryName": @json($documentationTitle),
            operationsSorter: @json($operationsSorter ?? null),
            configUrl: @json($configUrl ?? null),
            validatorUrl: @json($validatorUrl ?? null),
            oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",

            requestInterceptor: function(request) {
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],

            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],

            layout: "StandaloneLayout",
            docExpansion : @json(config('l5-swagger.defaults.ui.display.doc_expansion', 'none')),
            deepLinking: true,
            filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
            persistAuthorization: @json((bool) config('l5-swagger.defaults.ui.authorization.persist_authorization')),

        })

        window.ui = ui

        @if(in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes'), 'type')))
        ui.initOAuth({
            usePkceWithAuthorizationCodeGrant: @json((bool) config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant'))
        })
        @endif
    }
</script>
</body>
</html>
