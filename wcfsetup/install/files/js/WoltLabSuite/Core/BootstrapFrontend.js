/**
 * Bootstraps WCF's JavaScript with additions for the frontend usage.
 *
 * @author  Alexander Ebert
 * @copyright  2001-2019 WoltLab GmbH
 * @license  GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @module  WoltLabSuite/Core/BootstrapFrontend
 */
define(["require", "exports", "tslib", "./BackgroundQueue", "./Bootstrap", "./Controller/Style/Changer", "./Controller/Popover", "./Ui/User/Ignore", "./Ui/Page/Header/Menu", "./Ui/Message/UserConsent"], function (require, exports, tslib_1, BackgroundQueue, Bootstrap, ControllerStyleChanger, ControllerPopover, UiUserIgnore, UiPageHeaderMenu, UiMessageUserConsent) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.setup = void 0;
    BackgroundQueue = tslib_1.__importStar(BackgroundQueue);
    Bootstrap = tslib_1.__importStar(Bootstrap);
    ControllerStyleChanger = tslib_1.__importStar(ControllerStyleChanger);
    ControllerPopover = tslib_1.__importStar(ControllerPopover);
    UiUserIgnore = tslib_1.__importStar(UiUserIgnore);
    UiPageHeaderMenu = tslib_1.__importStar(UiPageHeaderMenu);
    UiMessageUserConsent = tslib_1.__importStar(UiMessageUserConsent);
    /**
     * Initializes user profile popover.
     */
    function _initUserPopover() {
        ControllerPopover.init({
            className: "userLink",
            dboAction: "wcf\\data\\user\\UserProfileAction",
            identifier: "com.woltlab.wcf.user",
        });
        // @deprecated since 5.3
        ControllerPopover.init({
            attributeName: "data-user-id",
            className: "userLink",
            dboAction: "wcf\\data\\user\\UserProfileAction",
            identifier: "com.woltlab.wcf.user.deprecated",
        });
    }
    /**
     * Bootstraps general modules and frontend exclusive ones.
     */
    function setup(options) {
        // Modify the URL of the background queue URL to always target the current domain to avoid CORS.
        options.backgroundQueue.url = window.WSC_API_URL + options.backgroundQueue.url.substr(window.WCF_PATH.length);
        Bootstrap.setup({ enableMobileMenu: true });
        UiPageHeaderMenu.init();
        if (options.styleChanger) {
            ControllerStyleChanger.setup();
        }
        if (options.enableUserPopover) {
            _initUserPopover();
        }
        BackgroundQueue.setUrl(options.backgroundQueue.url);
        if (Math.random() < 0.1 || options.backgroundQueue.force) {
            // invoke the queue roughly every 10th request or on demand
            BackgroundQueue.invoke();
        }
        if (globalThis.COMPILER_TARGET_DEFAULT) {
            UiUserIgnore.init();
        }
        UiMessageUserConsent.init();
    }
    exports.setup = setup;
});
