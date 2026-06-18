const proxy = process.env.BROWSERSYNC_PROXY || "localhost/";

module.exports = {
	"proxy": proxy,
	"notify": false,
	"files": ["./css/*.min.css", "./js/*.min.js", "./**/*.php"]
};