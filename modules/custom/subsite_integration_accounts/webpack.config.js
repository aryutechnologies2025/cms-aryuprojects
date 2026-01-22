const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
    mode:'production',
    entry: './src/js/main.js',
    output: {
        filename: 'main.js',
        path: path.resolve(__dirname, './dist/js/'),
    },
    module: {
        rules: [
            // ... other rules
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            },
        ]
    },
    plugins: [
        // make sure to include the plugin!
        new VueLoaderPlugin()
    ],
    resolve: {
        modules: ["./node_modules"],
        alias: {
            vue:'vue/dist/vue.esm-bundler.js',
            VueComponents: path.resolve(__dirname,'./src/js/components/')
        },
        extensions: ['.js','.vue']
    }
};