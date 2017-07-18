// special wp initialisation
// 'wp.pluginsUrl' is set by wordpress
dwv.wp = dwv.wp || {};
dwv.wp.init_was_called = false;
dwv.wp.init = function ()
{
    // avoid multiple calls
    if ( dwv.wp.init_was_called ) {
        return;
    }
    dwv.wp.init_was_called = true;
    
    // image decoders (for web workers)
    dwv.image.decoderScripts = {
        "jpeg2000": wp.pluginsUrl + "/dicom-support/ext/pdfjs/decode-jpeg2000.js",
        "jpeg-lossless": wp.pluginsUrl + "/dicom-support/ext/rii-mango/decode-jpegloss.js",
        "jpeg-baseline": wp.pluginsUrl + "/dicom-support/ext/pdfjs/decode-jpegbaseline.js"
    };
    // check browser support
    dwv.browser.check();
    // initialise i18n
    dwv.i18nInitialise("auto", wp.pluginsUrl + "/dicom-support");
}
