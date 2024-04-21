const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');
const wcDepMap = {
    '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    '@woocommerce/settings'       : ['wc', 'wcSettings']
};

const wcHandleMap = {
    '@woocommerce/blocks-registry': 'wc-blocks-registry',
    '@woocommerce/settings'       : 'wc-settings'
};

const requestToExternal = (request) => {
    if (wcDepMap[request]) {
        return wcDepMap[request];
    }
};

const requestToHandle = (request) => {
    if (wcHandleMap[request]) {
        return wcHandleMap[request];
    }
};

// Export configuration.
module.exports = {
    ...defaultConfig,
    entry: {
        'woocommerce-blocks/blocks': '/resources/js/woocommerce-blocks/index.js',
       /* 'woocommerce-blocks/bit/blocks': '/resources/js/woocommerce-blocks/bit/index.js',
        'woocommerce-blocks/googlepay/blocks': '/resources/js/woocommerce-blocks/googlepay/index.js',
        'woocommerce-blocks/applepay/blocks': '/resources/js/woocommerce-blocks/applepay/index.js',
        'woocommerce-blocks/multipss/blocks': '/resources/js/woocommerce-blocks/multipss/index.js',*/
    },

    output: {
        path: path.resolve( __dirname, 'block/dist/js' ),
        filename: '[name].js',
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) =>
                plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new WooCommerceDependencyExtractionWebpackPlugin({
            requestToExternal,
            requestToHandle
        })
    ]
};
