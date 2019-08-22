$(function () {


});

$(document).on('change', '.inputFile', function (e) {

    e.preventDefault();

    var $job = $(this).attr('data-job');
    var $ft = $(this).attr('data-form');
    var form = $('#inv' + $ft + 'Form_' + $job)[0];
    var $sendType = $('#rep' + $ft + 'Type_' + $job);
    console.log($ft);

    var $btnA = $('#dropA_' + $job);
    var $btnI = $('#dropI_' + $job);
    var $btnZ = $('#dropZ_' + $job);
    var $htmlA = $btnA.html();
    var $htmlI = $btnI.html();
    var $htmlZ = $btnZ.html();

    $btnA.addClass('disabled').html('<i class="fa fa-spin fa-spinner"></i> Lade...');
    $btnI.addClass('disabled').html('<i class="fa fa-spin fa-spinner"></i> Lade...');
    $btnZ.addClass('disabled').html('<i class="fa fa-spin fa-spinner"></i> Lade...');

    var $url = $('#ajax-route-upload-file').val();
    $.ajax({
        url: $url,
        data: new FormData(form),// the formData function is available in almost all new browsers.
        type: "post",
        contentType: false,
        processData: false,
        cache: false,
        dataType: "json", // Change this according to your response from the server.
        error: function (data) {
            openNoty(data.result, data.message);
            $btnA.removeClass('disabled').html($htmlA);
            $btnI.removeClass('disabled').html($htmlI);
            $btnZ.removeClass('disabled').html($htmlZ);
            $sendType.val('');
        },
        success: function (data) {
            $btnA.removeClass('disabled').html($htmlA);
            $btnI.removeClass('disabled').html($htmlI);
            $btnZ.removeClass('disabled').html($htmlZ);
            $sendType.val('');

            if (data.result === 'success') {

                if (data.extra.type === "pdfinvoice") {
                    openNoty(data.result, data.message);
                    if (data.result === 'success') {
                        location.reload();
                    }
                }
                if (data.extra.type === "invoice") {
                    if (data.result === 'success') {
                        openNoty(data.result, 'Rechnung an 3C übermittelt, Auftrag abgeschlossen.');
                        $('#row_' + $job).remove();
                    } else {
                        openNoty(data.result, data.message);
                    }
                }

                if (data.extra.type === "attachment") {
                    openNoty(data.result, data.message);
                }

                if (data.extra.type === "report") {
                    var $jobLabel = $('#label_' + $job);
                    openNoty(data.result, data.message);
                    var counter = parseInt($jobLabel.html());
                    counter = counter + 1;
                    $jobLabel.html(counter);
                }
            } else {
                openNoty(data.result, data.message);
            }

        },
        complete: function () {
            console.log("Request finished.");

        }
    });
});

$(document).on('click', '.js-save-manual', function (e) {

    e.preventDefault();
    var $btn = $(this);
    var $html = $btn.html();
    var $url = $btn.attr('data-url');

    var referenceNo = $('#referenceNo').val();
    var insuranceContractNo = $('#insuranceContractNo').val();
    var insuranceDamageNo = $('#insuranceDamageNo').val();
    var insuranceVuNo = $('#insuranceVuNo').val();
    var insuranceId = $('#insuranceName').val();

    if (!referenceNo.length || !insuranceContractNo.length || !insuranceDamageNo.length || !insuranceVuNo.length) {
        openNoty('error', 'Bitte alle Felder ausfüllen.');
        return false;
    }

    if(insuranceId === 'NONE'){
        openNoty('error', 'Bitte eine Versicherung wählen.');
        return false;
    }

    if($btn.hasClass('disabled')){
        return false;
    }

    $btn.addClass('disabled').html('<i class="fa fa-spin fa-spinner"></i> Speichere...');

    /* Ajax Call */
    $.get($url, {
        referenceNo: referenceNo,
        insuranceContractNo: insuranceContractNo,
        insuranceDamageNo: insuranceDamageNo,
        insuranceVuNo: insuranceVuNo,
        insuranceId: insuranceId
    })
        .done(function (data) {
            openNoty(data.result, data.message);
            if (data.result === 'success') {
               $('input[type="text"]').val('');
            }
            $btn.removeClass('disabled').html($html);
        })
        .error(function () {
            openNoty(data.result, data.message);
            $btn.removeClass('disabled').html($html);
        })
    ;
});

$(document).on('click', '.js-select-file', function (e) {

    e.preventDefault();
    var $btn = $(this);
    var $type = $btn.attr('data-type');
    var $ft = $btn.attr('data-form');
    var $desc = $btn.html();
    var $job = $btn.attr('data-job');
    var $sendType = $('#rep' + $ft + 'Type_' + $job);
    var $d = $btn.attr('data-desc');

    $sendType.val($type);

    x0p({
        title: 'Bericht versenden?',
        text: 'Möchten Sie <strong>' + $d + '</strong> vom Typ \'<strong>' + $desc + '</strong>\' versenden?',
        html: true,
        maxHeight: '200px',
        maxWidth: '500px',
        animationType: 'fadeIn',
        buttons: [
            {
                type: 'error',
                text: 'Abbrechen',
                showLoading: false
            },
            {
                type: 'ok',
                text: 'Datei wählen',
                showLoading: false
            }
        ]
    }).then(
        function (data) {
            if (data.button === 'ok') {
                $('#inpt'+$ft+'_' + $job).trigger('click');
            } else {
                $sendType.val('');
            }
        });


});

$(document).on('click', '.js-receipt-job', function (e) {

    e.preventDefault();

    var $btn = $(this);
    var $id = $btn.attr('data-id');
    var $job = $btn.attr('data-job');


    x0p({
        title: 'Auftrag annehmen?',
        text: 'Wollen Sie den Auftrag ' + $job + ' quittieren?',
        html: true,
        maxHeight: '200px',
        maxWidth: '500px',
        animationType: 'fadeIn',
        buttons: [
            {
                type: 'error',
                text: 'Abbrechen',
                showLoading: false
            },
            {
                type: 'warning',
                text: 'Auftrag ablehnen',
                showLoading: false
            },
            {
                type: 'ok',
                text: 'Auftrag annehmen',
                showLoading: true
            }
        ]
    }).then(
        function (data) {
            if (data.button === 'ok') {

                /* Ajax Call */
                $.post($btn.attr('data-url'), {
                    id: $id,
                    status: 'accept',
                    reason: 'OK'
                })
                    .done(function (r) {
                        if (r.result === 'success') {
                            x0p('Erfolg',
                                'Auftrag angenommen, Quittung übermittelt',
                                'ok', false).then(function (inner) {
                                $btn.remove();
                                $('#jobRow_' + $id).remove();
                            });
                        } else {
                            x0p('Fehler',
                                r.message,
                                'error', false);
                        }
                    })
                    .fail(function () {
                        x0p('Fehler',
                            'Fehler bei der Quttierung, bitte versuchen Sie es erneut',
                            'error', false);
                    })
                ;
            }
            if (data.button === 'warning') {

                x0p({
                    title: 'Begründung',
                    text: 'Bitte wählen Sie einen Grund<br />' +
                        '<select id="selectReason" data-id="' + $id + '">' +
                        '<option value="NONE" >-- Bitte wählen --</option>' +
                        '<option value="01: Kunde möchte gar keinen Sanierer" >01: Kunde möchte gar keinen Sanierer</option>' +
                        '<option value="02: Kunde möchte diesen Sanierer nicht">02: Kunde möchte diesen Sanierer nicht</option>' +
                        '<option value="03: Kunde nicht erreichbar">03: Kunde nicht erreichbar</option>' +
                        '<option value="04: Sanierer hat keine Kapazität">04: Sanierer hat keine Kapazität</option>' +
                        '</select>',
                    html: true,
                    maxHeight: '200px',
                    maxWidth: '500px',
                    animationType: 'fadeIn',
                    buttons: [
                        {
                            type: 'error',
                            text: 'Abbrechen',
                            showLoading: false
                        },
                        {
                            type: 'warning',
                            text: 'Auftrag ablehnen',
                            showLoading: true
                        }
                    ]
                }).then(
                    function (dataInner) {
                        if (dataInner.button === 'warning') {

                            var $reason = $('#reason_' + $id);

                            /* Ajax Call */
                            $.post($btn.attr('data-url'), {
                                id: $id,
                                status: 'decline',
                                reason: $reason.val()
                            })
                                .done(function (r) {
                                    if (r.result === 'success') {
                                        x0p('Erfolg',
                                            'Auftrag abgelehnt, Quittung übermittelt',
                                            'ok', false).then(function (inner) {
                                            $btn.remove();
                                            $('#jobRow_' + $id).remove();
                                        });
                                    } else {
                                        x0p('Fehler',
                                            r.message,
                                            'error', false);
                                    }
                                })
                                .fail(function () {
                                    x0p('Fehler',
                                        'Fehler bei der Quttierung, bitte versuchen Sie es erneut',
                                        'error', false);
                                })
                            ;
                        }
                    });
            }
        });
});

$(document).on('change', '#selectReason', function (e) {

    e.preventDefault();
    var $select = $(this);
    var $id = $select.attr('data-id');
    $('#reason_' + $id).val($('#selectReason option:selected').text());

});
