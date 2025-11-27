(function() {
    const lang = navigator.language || navigator.userLanguage;
    const code = lang.substring(0,2).toLowerCase();

    if (window.location.pathname === "/") {
        if (code === "nl") {
            window.location.href = "/nl/";
        } else {
            window.location.href = "/en/";
        }
    }
})();
