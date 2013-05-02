<div id="packageInstallationDialogContainer">
	<header class="box48 boxHeadline">
		<span class="icon icon48 icon-spinner"></span>
		
		<div>
			<h1>Paket &raquo;{$queue->packageName}&laquo; wird deinstalliert &hellip;</h1><!-- ToDo: Language variables -->
			<p>Aktueller Schritt: <span id="packageInstallationAction">{lang}wcf.package.uninstallation.step.prepare{/lang}</span></p>
			<p><progress id="packageInstallationProgress" value="0" max="100">0%</progress></p>
		</div>
	</header>
	
	<div id="packageInstallationInnerContentContainer" style="display: none;">
		<div id="packageInstallationInnerContent"></div>
	</div>
</div>