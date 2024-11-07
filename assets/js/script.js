function calculateDifference() {
    const currentPhonePrice = parseFloat(document.getElementById('currentPhone').value);
    const desiredPhonePrice = parseFloat(document.getElementById('desiredPhone').value);
    const difference = desiredPhonePrice - currentPhonePrice;

    const resultElement = document.getElementById('result');
    var message = '';
    var topUpAmount = 0;
    if (difference > 0) {
        topUpAmount = difference.toLocaleString();
        message += `You need to pay: ${topUpAmount} ugx to upgrade to the desired iPhone.`;
        document.getElementById('checkoutButton').style.display = 'block';
    } else {
        topUpAmount = 0;
        message += `A swap cannot be performed for the selected options.`;
        document.getElementById('checkoutButton').style.display = 'none';
    }
    resultElement.innerText = message;
    document.getElementById('checkoutButton').setAttribute('data-top-up-amount', topUpAmount);

}

function goToCheckout(event) {
    event.preventDefault();
    const topUpAmount = document.getElementById('checkoutButton').getAttribute('data-top-up-amount');
    const product_id = document.getElementById('product_id').value;

    jQuery.post(
        wcis_params.ajaxurl,
        {
            action: 'add_swap_product_to_cart',
            product_id: product_id,
            top_up_amount: topUpAmount
        }).done(function(res) {
        window.location.href = wcis_params.checkout_url;
    });
}