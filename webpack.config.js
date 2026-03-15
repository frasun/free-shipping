const path = require('path');
const FILENAME = 'chocante-free-shipping';

module.exports = {
	mode: 'production',
	entry: {
		'js': `./js/${FILENAME}.js`,
	},
	output: {
		path: path.resolve(__dirname, 'js'),
		filename: `${FILENAME}.min.js`,
	}
};