let mix = require("laravel-mix");

mix
    .options({
        publicPath: 'src/assets/dist',
        resourceRoot: "/layouts/products/dist",
    })
    .js("src/assets/js/products.js", "js/products-module.min.js")
    .sass("src/assets/scss/products.scss", "css/products-module.min.css")
    .version();
