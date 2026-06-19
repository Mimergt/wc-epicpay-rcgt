const settings = window.wc.wcSettings.getSetting( 'epicpay_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'EpicPay', 'epicpay' );
const Content = () => {
    let desc =  window.wp.htmlEntities.decodeEntities( settings.description || '' );
    return desc;
};
const Icon = () => {
    let icon =  window.wp.htmlEntities.decodeEntities( settings.icon || '' );
    return icon;
};
const Block_Gateway = {
    name: 'epicpay',
    label: label,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    placeOrderButtonLabel: 'Pagar de forma segura',
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );