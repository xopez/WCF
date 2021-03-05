import * as Environment from "../../../Environment";
import * as UiAlignment from "../../Alignment";
import UiCloseOverlay from "../../CloseOverlay";
import UiDropdownSimple from "../../Dropdown/Simple";
import * as UiScreen from "../../Screen";

const _backdrop = document.createElement("div");
let _isMobile = false;
const _pageHeader = document.getElementById("pageHeader")!;
const _pageHeaderPanel = document.getElementById("pageHeaderPanel")!;
const _pageHeaderSearch = document.getElementById("pageHeaderSearch")!;
const _pageHeaderSearchInput = document.getElementById("pageHeaderSearchInput") as HTMLInputElement;
let _scrollTop: number | null = null;
const _userPanelSearchButton = document.getElementById("userPanelSearchButton") as HTMLAnchorElement;

function openSearchBar(): void {
  window.WCF.Dropdown.Interactive.Handler.closeAll();

  _pageHeader.classList.add("searchBarOpen");
  _pageHeaderSearch.classList.add("open");
  _userPanelSearchButton.parentElement!.classList.add("open");

  if (Environment.platform() === "ios") {
    _scrollTop = document.body.scrollTop;
    UiScreen.scrollDisable();
  }

  if (_isMobile) {
    document.body.appendChild(_backdrop);

    _pageHeaderSearch.style.setProperty("top", "0");
  } else {
    const topMenu = document.getElementById("topMenu")!;

    // calculate value for `right` on desktop
    UiAlignment.set(_pageHeaderSearch, topMenu, {
      horizontal: "right",
    });

    _pageHeaderSearch.style.setProperty("top", `${_pageHeaderPanel.clientHeight}px`);
  }

  _pageHeaderSearchInput.focus();

  if (Environment.platform() === "ios") {
    document.body.scrollTop = 0;
  }

  window.setTimeout(() => {
    const offset = _pageHeaderSearchInput.value.length;
    _pageHeaderSearchInput.selectionStart = offset;
    _pageHeaderSearchInput.selectionEnd = offset;
  }, 1);

  UiCloseOverlay.add("WoltLabSuite/Core/Ui/Page/Header/Search", () => {
    if (_pageHeader.classList.contains("searchBarForceOpen")) {
      return;
    }

    closeSearchBar();
  });
}

function closeSearchBar(): void {
  _backdrop.remove();

  _pageHeader.classList.remove("searchBarOpen");
  _userPanelSearchButton.parentElement!.classList.remove("open");

  ["bottom", "left", "right", "top"].forEach((propertyName) => {
    _pageHeaderSearch.style.removeProperty(propertyName);
  });

  _pageHeaderSearchInput.blur();

  // close the scope selection
  const scope = _pageHeaderSearch.querySelector(".pageHeaderSearchType")!;
  UiDropdownSimple.close(scope.id);

  UiCloseOverlay.remove("WoltLabSuite/Core/Ui/Page/Header/Search");

  if (Environment.platform() === "ios") {
    UiScreen.scrollEnable();

    if (_scrollTop) {
      document.body.scrollTop = _scrollTop;
      _scrollTop = null;
    }
  }
}

function setupMobile(): void {
  _isMobile = true;

  setupBackdrop();

  const pageHeaderMobileSearch = document.getElementById("pageHeaderMobileSearch")!;
  pageHeaderMobileSearch.addEventListener("click", (event) => toggleSearchBar(event));
}

function toggleSearchBar(event: Event): void {
  event.preventDefault();
  event.stopPropagation();

  if (_pageHeader.classList.contains("searchBarOpen")) {
    closeSearchBar();
  } else {
    openSearchBar();
  }
}

function setupBackdrop(): void {
  _backdrop.classList.add("pageHeaderSearchBackdrop");
  _backdrop.addEventListener("click", (event) => {
    event.preventDefault();

    closeSearchBar();
  });
}

function rebuildPosition(isMobile: boolean): void {
  _isMobile = isMobile;
}

export function setup(): void {
  _userPanelSearchButton.addEventListener("click", (event) => toggleSearchBar(event));
  _pageHeaderSearch.addEventListener("click", (event) => event.stopPropagation());

  UiScreen.on("screen-md-down", {
    match: () => rebuildPosition(true),
    unmatch: () => rebuildPosition(false),
    setup: () => setupMobile(),
  });
}
