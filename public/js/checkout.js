document.addEventListener('DOMContentLoaded', function () {
    const paymentRadios = document.querySelectorAll(
        'input[name="paymentMethod"]'
    );

    const mobileMoneyOptions = document.getElementById('mobileMoneyOptions');
    const paymentOperator = document.getElementById('paymentOperator');
    const depositSummary = document.getElementById('depositSummary');

    function updatePaymentDisplay() {
        const selectedPayment = document.querySelector(
            'input[name="paymentMethod"]:checked'
        );

        const isMobileMoney =
            selectedPayment &&
            selectedPayment.value === 'mobile_money_demo';

        mobileMoneyOptions.classList.toggle('hidden', !isMobileMoney);
        depositSummary.classList.toggle('hidden', !isMobileMoney);

        paymentOperator.disabled = !isMobileMoney;
        paymentOperator.required = Boolean(isMobileMoney);

        if (!isMobileMoney) {
            paymentOperator.value = '';
        }
    }

    paymentRadios.forEach(function (radio) {
        radio.addEventListener('change', updatePaymentDisplay);
    });

    updatePaymentDisplay();
});
