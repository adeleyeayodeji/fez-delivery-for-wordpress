const path = require("path");
const TerserPlugin = require("terser-webpack-plugin");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const defaultConfig = require("@wordpress/scripts/config/webpack.config");

module.exports = {
	...defaultConfig,
	entry: {
		fezdelivery: "./src/main.jsx",
		fezdeliveryhome: "./src/frontend.jsx",
	},

	output: {
		path: path.resolve(__dirname, "assets/js"),
		filename: "[name].min.js",
		publicPath: "../../",
		assetModuleFilename: "images/[name][ext][query]",
	},

	resolve: {
		extensions: [".js", ".jsx"],
	},

	module: {
		...defaultConfig.module,
		rules: [
			//...defaultConfig.module.rules,
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: "babel-loader",
			},
			{
				test: /\.(css|scss)$/,
				exclude: /node_modules/,
				use: [
					"style-loader",
					{
						loader: MiniCssExtractPlugin.loader,
						options: {
							esModule: false,
						},
					},
					{
						loader: "css-loader",
					},
					"sass-loader",
				],
			},
			{
				test: /\.svg/,
				type: "asset/inline",
			},
			{
				test: /\.(png|jpg|gif)$/,
				type: "asset/resource",
				generator: {
					filename: "../images/[name][ext][query]",
				},
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/,
				type: "asset/resource",
				generator: {
					filename: "../fonts/[name][ext][query]",
				},
			},
		],
	},

	plugins: [
		...defaultConfig.plugins,
		new CleanWebpackPlugin(),
		new MiniCssExtractPlugin({
			filename: "../css/[name].min.css",
		}),
	],

	optimization: {
		minimize: true,
		minimizer: [
			new TerserPlugin({
				terserOptions: {
					format: {
						comments: false,
					},
				},
				extractComments: false,
			}),
		],
	},
};
