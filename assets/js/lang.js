/*
 * S4W Global — Language Helper
 * Handles manual NL/EN toggle and prevents reload loops.
 */

document.addEventListener("DOMContentLoaded", function () {

    const links = document.querySelectorAll(".lang-switch a");

    links.forEach(link => {
        link.addEventListener("click", function (e) {
            // Allow normal navigation, but prevent flickering
            document.body.classList.add("fade-out");
        });
    });

});
