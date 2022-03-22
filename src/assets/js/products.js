import "./vendor"

$(document).ready(function() {
    new Sortable(document.getElementById('shop-products-sort-grid'), {
        animation: 150,
        ghostClass: 'blue-background-class',
        scroll: true,
        scrollSensitivity: 150,
        bubbleScroll: true,
        forceAutoScrollFallback: true,
        scrollSpeed: 20
    });
});
