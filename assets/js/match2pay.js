const PAYMENT_ID = 'match2pay'
const selector = '.wc_payment_method.payment_method_match2pay';
const WATCHER_INTERVAL_MS = 30000

jQuery(function ($) {
    const isPayForOrderForm = window.location.href.indexOf('pay_for_order') !== -1;

    function showPlaceOrderButton(forceText = false) {
        $('#place_order').css('opacity', 1.0);
        $('#place_order').css('visibility', 'initial');
        if (forceText) {
            $('#place_order').text(forceText);
        }
    }
    function hidePlaceOrderButton() {
        $('#place_order').css('opacity', 0.25);
        $('#place_order').css('visibility', 'hidden');
    }

    window.match2pay_submitForm = function (delay = 1500) {
        $('#place_order').css('opacity', 1.0);
        $('#place_order').css('visibility', 'initial');
        const timer = setTimeout(function () {
            let submitBtn = document.getElementById('place_order');
            if (submitBtn) {
                submitBtn.click();
            } else {
                console.warn('Missing submit button. Could not submit form to place order.');
            }
        }, delay);
    }

    window.match2pay_ajax_action = function (url, callback, _method, _data, sendJSON = true) {
        let xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                try {
                    var data = JSON.parse(xmlhttp.responseText);
                } catch (err) {
                    console.warn(err.message + " in " + xmlhttp.responseText, err);
                    return;
                }
                callback(data);
            }
        };
        xmlhttp.open(_method, url, true);
        if (!sendJSON) {
            xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        xmlhttp.send(_data);
    }

    window.match2pay_getPaymentFormData = function () {
        const ajaxUrlNode = document.getElementById('match2pay-payment-gateway-payment-form-request-ajax-url');
        const ajaxUrl = ajaxUrlNode
            ? ajaxUrlNode.getAttribute('data-value')
            : null;

        if (!ajaxUrl) {
            console.warn('missing ajax url for payment form data request');
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return;
        }

        const url = ajaxUrl;
        const method = 'POST';
        let data = $(selector).closest('form').serialize();
        match2pay_ajax_action(url, match2pay_getPaymentFormDataCallback, method, data, false);
    }

    window.match2pay_getWatcherData = function () {
        const ajaxUrlNode = document.getElementById('match2pay-payment-gateway-watcher');
        const ajaxUrl = ajaxUrlNode
            ? ajaxUrlNode.getAttribute('data-value')
            : null;

        if (!ajaxUrl) {
            console.warn('missing ajax url for payment form data request');
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return;
        }

        const url = ajaxUrl;
        const method = 'POST';
        let data = $(selector).closest('form').serialize();
        match2pay_ajax_action(url, match2pay_watcherCallback, method, data, false);
    }

    function showError(errorMessage, selector) {
        var $container = $('.woocommerce-notices-wrapper, form.checkout');

        if (!$container || !$container.length) {
            $(selector).prepend(errorMessage);
            return;
        } else {
            $container = $container.first();
        }

        // Adapted from https://github.com/woocommerce/woocommerce/blob/ea9aa8cd59c9fa735460abf0ebcb97fa18f80d03/assets/js/frontend/checkout.js#L514-L529
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        $container.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>');
        $container.find('.input-text, select, input:checkbox').trigger('validate').blur();

        var scrollElement = $('.woocommerce-NoticeGroup-checkout');
        if (!scrollElement.length) {
            scrollElement = $container;
        }

        if ($.scroll_to_notices) {
            $.scroll_to_notices(scrollElement);
        } else {
            // Compatibility with WC <3.3
            $('html, body').animate({
                scrollTop: ($container.offset().top - 100)
            }, 1000);
        }

        $(document.body).trigger('checkout_error');
    }

    window.match2pay_validateCheckout = function (elem) {
        $('#match2pay_embedded_payment_form_btn').hide();
        $('#match2pay_embedded_payment_form_loading_txt').show();

        if (elem.classList.contains('match2pay-order-pay')) {
            let callback = match2pay_getPaymentFormDataCallback;
            let url = match2pay_params.ajax_url + '?action=match2pay_orderpay_payment_request';
            let data = $(selector).closest('form').serialize();
            data += '&order_id=' + elem.dataset.id;
            console.log(data)
            // let data = 'order_id=' + elem.dataset.id;

            var xmlHttp = new XMLHttpRequest();
            xmlHttp.open("POST", url, false); // false for synchronous request
            xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xmlHttp.send(data);
            try {
                var response = JSON.parse(xmlHttp.responseText);
            } catch (err) {
                console.warn(err.message + " in " + xmlHttp.responseText, err);
                return;
            }
            callback(response);
            if (xmlHttp.responseText != null) {
                match2pay_displayPaymentForm();
            } else {
                alert('Something went wrong');
            }
            return true;
        }

        let checkoutCheckUrlNode = document.getElementById('match2pay-payment-gateway-start-checkout-check-url');

        if (checkoutCheckUrlNode) {
            let url = checkoutCheckUrlNode.getAttribute('data-value');
            if (url) {
                let callback = match2pay_validateCheckoutCallback;

                // Clear any errors from previous attempt.
                $('.woocommerce-error', selector).remove();

                let data = $(selector).closest('form').serialize();

                // Call URL
                match2pay_ajax_action(url, callback, "POST", data, false);
            }
        } else {
            console.error('Checkout validation callback URL not found.');
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
        }
    }

    function match2pay_validateCheckoutCallback(response) {
        if (response.data.messages && response.data.messages.error && response.data.messages.error.length > 0) {

            let messageItems = response.data.messages.error.map(function (message) {
                return '<li>' + message.notice + '</li>';
            }).join('');

            showError('<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', selector);
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;

        }
        if (response.data && response.success === false) {
            let messageItems = response.data.messages.map(function (message) {
                return '<li>' + message + '</li>';
            }).join('');

            showError('<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', selector);
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;
        } else if (response.result && response.result === 'failure' && response.messages && typeof response.messages === "string") {
            showError(response.messages, selector);
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;
        }

        // Clear any errors from previous attempt.
        $('.woocommerce-error').remove();

        match2pay_getPaymentFormData();
    }

    function match2pay_freeze_checkout_form() {
        var billingFieldsDiv = document.querySelector('.woocommerce-billing-fields');

        // Apply the blur effect using inline styles
        billingFieldsDiv.style.webkitFilter = 'blur(5px)';
        billingFieldsDiv.style.mozFilter = 'blur(5px)';
        billingFieldsDiv.style.oFilter = 'blur(5px)';
        billingFieldsDiv.style.msFilter = 'blur(5px)';
        billingFieldsDiv.style.filter = 'blur(1px)';
        billingFieldsDiv.style.pointerEvents = 'none';
        billingFieldsDiv.style.position = 'relative';
    }

    window.match2pay_getPaymentFormDataCallback = function (response) {
        if (response.data && response.success === false) {
            var messageItems = response.data.messages.map(function (message) {
                return '<li>' + message + '</li>';
            }).join('');

            showError('<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', selector);
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;
        } else if (response.result && response.result === 'failure' && response.messages && typeof response.messages === "string") {
            showError(response.messages, selector);
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;
        }

        if (!response || !response.status || response.status !== 'ok') {
            console.warn('error occured when requesting payment form data');
            $('#match2pay_embedded_payment_form_btn').show();
            $('#match2pay_embedded_payment_form_loading_txt').hide();
            return null;
        }

        if ($('[name=match2pay_currency][type=hidden]').length === 0) {
            $('#match2pay_embedded_payment_form_btn').text('Change Currency');
            $('#match2pay_embedded_payment_form_btn').show();
        }

        $('#match2pay_embedded_payment_form_loading_txt').hide();
        if (!isPayForOrderForm) {
            match2pay_freeze_checkout_form();
        }

        match2pay_createHiddenInputData('match2pay_paymentId', response.payment_form_data.paymentId);
        match2pay_displayPaymentForm();
    }

    window.match2pay_restorePayment = function () {
        const previousPayment = $('#match2pay-payment-form').data('payment-id');
        match2pay_createHiddenInputData('match2pay_paymentId', previousPayment);
        if (!isPayForOrderForm){
            match2pay_freeze_checkout_form();
        }
        match2pay_displayPaymentForm();
    }

    window.match2pay_createHiddenInputData = function (inputId, inputValue) {
        let hiddenInput;

        if (!!document.getElementById(inputId)) {
            hiddenInput = document.getElementById(inputId);
            hiddenInput.value = inputValue;
        } else {
            hiddenInput = document.createElement("input");
            hiddenInput.setAttribute("id", inputId);
            hiddenInput.setAttribute("name", inputId);
            hiddenInput.setAttribute("type", "hidden");
            hiddenInput.value = inputValue;

            let checkoutForm = document.getElementsByClassName('checkout woocommerce-checkout')['checkout'];
            if (checkoutForm){
                checkoutForm.appendChild(hiddenInput);
            }

            let orderReviewForm = document.getElementById('order_review');
            if (orderReviewForm){
                orderReviewForm.appendChild(hiddenInput);
            }
        }
    }
    let _watcher = null
    window.match2pay_displayPaymentForm = function () {
        if (_watcher) {
            clearInterval(_watcher);
        }

        match2pay_getWatcherData();
        _watcher = setInterval(() => {
            match2pay_getWatcherData();
        }, WATCHER_INTERVAL_MS)
    }

    window.match2pay_watcherCallback = function (response) {
        if (response.success === false) {
            console.log('error occured when requesting payment form data');
        }

        const data = response.data;

        if (data.result === 'success' && data.redirect){
            window.location.href = data.redirect;
            return;
        }

        const $canvas = $('#match2pay-qr')
        if ($canvas.data('address') !== data.walletAddress) {
            $canvas.empty();
            const qrcode = new QRCode($canvas[0], {
                text: data.walletAddress,
                width: 256,
                height: 256,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            $canvas.data('address', data.walletAddress)
        }

        let paymentStatus = data.paymentStatus;

        const paymentStatusElement = document.getElementById('match2pay-details');

        paymentStatusElement.innerHTML = data.fragment;

        if (paymentStatus === 'COMPLETED') {
            match2pay_submitForm();
        }
    }

    function paymentMethodAction() {
        if ($('#match2pay_currency[type=select]') && $('#match2pay_currency').select2) {
            $('#match2pay_currency').select2()
        }

        if ($('form[name="checkout"] input[name="payment_method"]:checked').val() === PAYMENT_ID) {
            hidePlaceOrderButton();
        } else if ($('#order_review input[name="payment_method"]:checked').val() === PAYMENT_ID) {
            hidePlaceOrderButton();
        } else {
            showPlaceOrderButton();
        }

        const previousPayment = $('#match2pay-payment-form').data('payment-id');
        if (previousPayment) {
            match2pay_restorePayment();
        }
    }

    $(document).on('click', '.match2pay-wallet-address', function () {
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val($(this).text()).select();
        document.execCommand("copy");
        $temp.remove();
        this.classList.add('match2pay-copied');
        setTimeout(() => {
            this.classList.remove('match2pay-copied');
        }, 300)
    })

    $(document).ready(function (e) {
        if ($('#order_review input[name="payment_method"]:checked').val() === PAYMENT_ID) {
            paymentMethodAction();
        }
        $('input[name="payment_method"]').change(function () {
            paymentMethodAction();
        });
        $('body').on('updated_checkout', function () {
            paymentMethodAction();
            $('input[name="payment_method"]').change(function () {
                paymentMethodAction();
            });
        });
    })
})

