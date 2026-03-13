<div class="scanner-page">

    <div class="scanner-feedback" id="js-feedback">
        <span id="js-feedback-txt">Aponte a câmera para o código de barras do crachá</span>
    </div>

    <div id="js-reader" class="scanner-reader"></div>

    <!-- Debug: último valor lido -->
    <div class="scanner-debug" id="js-debug" style="display:none;">
        <p class="scanner-debug__titulo">Último valor lido pela câmera:</p>
        <code id="js-debug-val"></code>
        <span id="js-debug-fmt"></span>
    </div>

    <!-- Fallback manual (sempre visível) -->
    <div class="scanner-manual">
        <p class="scanner-manual__label">Ou informe a matrícula manualmente:</p>
        <form id="js-form-manual" class="scanner-manual__form" autocomplete="off">
            <input type="text" id="js-matricula-manual" name="matricula"
                placeholder="Matrícula" class="scanner-manual__input">
            <button type="submit" class="scanner-manual__btn">Confirmar</button>
        </form>
    </div>

</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function() {
        var feedback = document.getElementById('js-feedback');
        var feedbackTxt = document.getElementById('js-feedback-txt');
        var debugBox = document.getElementById('js-debug');
        var debugVal = document.getElementById('js-debug-val');
        var debugFmt = document.getElementById('js-debug-fmt');
        var pausado = false;
        var scannerAtivo = false;

        function resetar() {
            feedback.className = 'scanner-feedback';
            feedbackTxt.textContent = 'Aponte a câmera para o código de barras do crachá';
            pausado = false;
            if (scannerAtivo) scanner.resume();
        }

        function mostrar(ok, msg) {
            feedback.className = 'scanner-feedback scanner-feedback--' + (ok ? 'ok' : 'erro');
            feedbackTxt.textContent = msg;
        }

        function mostrarDebug(texto, resultado) {
            debugBox.style.display = 'block';
            debugVal.textContent = JSON.stringify(texto);
            debugFmt.textContent = resultado && resultado.result && resultado.result.format ?
                '(' + resultado.result.format.formatName + ')' :
                '';
        }

        function tentarFocar() {
            var video = document.querySelector('#js-reader video');
            if (!video || !video.srcObject) return;
            var track = video.srcObject.getVideoTracks()[0];
            if (!track || !track.applyConstraints) return;

            var caps = track.getCapabilities ? track.getCapabilities() : null;
            if (caps && caps.focusMode) {
                track.applyConstraints({
                        advanced: [{
                            focusMode: 'continuous'
                        }]
                    })
                    .catch(function() {
                        // alguns navegadores recusam foco automático; ignora
                    });
            }
        }

        function processar(matricula, aoPausar, detalhe) {
            if (pausado) return;
            pausado = true;
            if (aoPausar) aoPausar();

            mostrarDebug(matricula, detalhe);

            fetch('/scanner/processar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'matricula=' + encodeURIComponent(matricula)
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.ok) {
                        mostrar(true, '✓ ' + data.nome + ' — ' + data.refeicao);
                    } else {
                        mostrar(false, '✗ ' + data.erro);
                    }
                    setTimeout(resetar, 4000);
                })
                .catch(function() {
                    mostrar(false, 'Erro de conexão. Tente novamente.');
                    setTimeout(resetar, 4000);
                });
        }

        /* ----- câmera ----- */
        var scanner = new Html5Qrcode('js-reader', {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.QR_CODE
            ],
            verbose: false,
            // Usa o detector nativo do navegador quando disponível (melhora leitura de 1D/2D em alguns dispositivos)
            useBarCodeDetectorIfSupported: true
        });

        scanner.start({
                facingMode: 'environment'
            }, {
                // fps mais baixo dá mais tempo de processamento e reduz chances de "pular" códigos
                fps: 15,
                // Área de leitura otimizada para barras longas (1D) — largura maior, altura menor
                qrbox: function(w, h) {
                    var boxW = Math.round(w * 0.9);
                    var boxH = Math.round(Math.min(h * 0.2, 180));

                    // Se a câmera for muito estreita (portrait), tenta manter um mínimo de altura
                    if (h / w > 1.6) {
                        boxH = Math.round(Math.min(h * 0.25, 220));
                    }

                    return {
                        width: boxW,
                        height: boxH
                    };
                },
                aspectRatio: 16 / 9,
                videoConstraints: {
                    facingMode: {
                        ideal: 'environment'
                    },
                    width: {
                        min: 1280,
                        ideal: 1920,
                        max: 3840
                    },
                    height: {
                        min: 720,
                        ideal: 1080,
                        max: 2160
                    },
                    advanced: [{
                            focusMode: 'continuous'
                        },
                        {
                            focusMode: 'auto'
                        }
                    ]
                }
            },
            function(texto, resultado) {
                processar(texto.trim(), function() {
                    scanner.pause(false);
                }, resultado);
            },
            function() {}
        ).then(function() {
            scannerAtivo = true;
            feedback.className = 'scanner-feedback';
            feedbackTxt.textContent = 'Câmera ativa — aponte para o código de barras';

            // Tenta forçar foco automático (quando suportado) e permite "tap to focus".
            tentarFocar();
            document.getElementById('js-reader').addEventListener('click', tentarFocar);
        }).catch(function() {
            document.getElementById('js-reader').style.display = 'none';
            feedback.className = 'scanner-feedback scanner-feedback--erro';
            feedbackTxt.textContent = 'Câmera indisponível — use o campo manual abaixo (requer HTTPS para câmera).';
        });

        /* ----- formulário manual ----- */
        document.getElementById('js-form-manual').addEventListener('submit', function(e) {
            e.preventDefault();
            var input = document.getElementById('js-matricula-manual');
            var val = input.value.trim();
            if (!val) return;
            processar(val, null, null);
            input.value = '';
        });
    })();
</script>