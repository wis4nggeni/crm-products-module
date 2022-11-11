let mix = require("laravel-mix");

mix
    // 2022 - temp fix of laravel-mix incompatibility with Apple Silicon
    // see https://github.com/laravel-mix/laravel-mix/issues/3027
    .disableNotifications()
    .options({
        publicPath: 'src/assets/dist',
        resourceRoot: "/layouts/products/dist",
    })
    .js("src/assets/js/products.js", "js/products-module.min.js")
    .sass("src/assets/scss/products.scss", "css/products-module.min.css")
    .version();
