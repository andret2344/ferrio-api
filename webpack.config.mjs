import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
	Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
	.setOutputPath('public/build/')
	.setPublicPath('/build')
	.addEntry('app', './assets/app.ts')
	.splitEntryChunks()
	.enableSingleRuntimeChunk()
	.cleanupOutputBeforeBuild()
	.enableBuildNotifications()
	.enableSourceMaps(!Encore.isProduction())
	.enableVersioning(Encore.isProduction())
	.configureBabel(config => {
		config.plugins.push('@babel/plugin-transform-class-properties');
		config.plugins.push(['babel-plugin-polyfill-corejs3', {method: 'usage-global', version: '3.49'}]);
	})
	.enableSassLoader()
	.enableTypeScriptLoader()
	.enableIntegrityHashes(Encore.isProduction())
;

export default await Encore.getWebpackConfig();
