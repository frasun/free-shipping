/**
 * Chocante Free Shipping
 * Checkout client validation
 */
const FREE_SHIPPING_FRAGMENT = "free_shipping_notice";

(function ($) {
	$(document.body).on("updated_checkout", function (event, data) {
		const fsNoticeExisting = document.querySelector(
			'[data-type="chocante-free-shipping"]',
		);
    const fsNoticeNew = data.fragments[FREE_SHIPPING_FRAGMENT];

		if (fsNoticeNew) {
			if (fsNoticeExisting) {
        fsNoticeExisting.outerHTML = fsNoticeNew;
			} else {
        const noticesWrapper = document.querySelector('.page-header + .woocommerce-notices-wrapper');
        if(noticesWrapper) {
          noticesWrapper.insertAdjacentHTML('afterbegin', fsNoticeNew);
        }
      }
		} else if (fsNoticeExisting) {
			fsNoticeExisting.remove();
		}
	});
})(jQuery);
