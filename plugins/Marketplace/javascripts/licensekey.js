/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(document).ready(function () {

    var $licenseKeyInput = $('.marketplace #license_key');
    var $licenseKeySubmit = $('.marketplace #submit_license_key');
    var $licenseKeyRemove = $('.marketplace #remove_license_key');

    function updateLicenseKey(action, licenseKey, onSuccessMessage)
    {
        var ajaxRequest = new ajaxHelper();
        ajaxRequest.setLoadingElement('#licenseKeyLoading');
        ajaxRequest.addParams({
            module: 'API',
            method: 'Marketplace.' + action,
            licenseKey: licenseKey,
            format: 'JSON'
        }, 'get');
        ajaxRequest.setCallback(function (response) {
            if (response && response.value) {
                var UI = require('piwik/UI');
                var notification = new UI.Notification();
                notification.show(onSuccessMessage, {context: 'success'});

                piwikHelper.redirect();
            }
        });
        ajaxRequest.send(false);
    }

    function setLicenseKeyEnabled(enabled)
    {
        $licenseKeySubmit.prop('disabled', !enabled);
    }

    $licenseKeyInput.on('change keyup', function () {
        var value = $(this).val();
        setLicenseKeyEnabled(!!value);
    });

    $licenseKeySubmit.on('click', function () {

        var value = $licenseKeyInput.val();

        if (!value) {
            return;
        }

        setLicenseKeyEnabled(false);
        updateLicenseKey('saveLicenseKey', value, _pk_translate('Marketplace_LicenseKeyUpdatedSuccess'));
    });

    $licenseKeyRemove.on('click', function () {
        updateLicenseKey('deleteLicenseKey', '', _pk_translate('Marketplace_LicenseKeyDeletedSuccess'));
    });
});
