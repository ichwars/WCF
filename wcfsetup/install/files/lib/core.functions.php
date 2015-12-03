<?php
/**
 * @author	Marcel Werk
 * @copyright	2001-2015 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @category	Community Framework
 */
namespace {
	use wcf\system\WCF;

	// set exception handler
	set_exception_handler([ WCF::class, 'handleException' ]);
	// set php error handler
	set_error_handler([ WCF::class, 'handleError' ], E_ALL);
	// set shutdown function
	register_shutdown_function([ WCF::class, 'destruct' ]);
	// set autoload function
	spl_autoload_register([ WCF::class, 'autoload' ]);

	// define escape string shortcut
	function escapeString($string) {
		return WCF::getDB()->escapeString($string);
	}

	// define DOCUMENT_ROOT on IIS if not set
	if (PHP_EOL == "\r\n") {
		if (!isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['SCRIPT_FILENAME'])) {
			$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
		}
		if (!isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['PATH_TRANSLATED'])) {
			$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
		}

		if (!isset($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);
			if (isset($_SERVER['QUERY_STRING'])) {
				$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
			}
		}
	}
}

// @codingStandardsIgnoreStart
namespace wcf\functions\exception {
	use wcf\system\WCF;
	use wcf\system\exception\IExtraInformationException;
	use wcf\system\exception\SystemException;
	use wcf\util\FileUtil;
	use wcf\util\StringUtil;

	function logThrowable($e) {
		$logFile = WCF_DIR . 'log/' . gmdate('Y-m-d', TIME_NOW) . '.txt';
		touch($logFile);

		// don't forget to update ExceptionLogViewPage, when changing the log file format
		$message = gmdate('r', TIME_NOW)."\n".
			'Message: '.str_replace("\n", ' ', $e->getMessage())."\n".
			'PHP version: '.phpversion()."\n".
			'WCF version: '.WCF_VERSION."\n".
			'Request URI: '.(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '')."\n".
			'Referrer: '.(isset($_SERVER['HTTP_REFERER']) ? str_replace("\n", ' ', $_SERVER['HTTP_REFERER']) : '')."\n".
			'User Agent: '.(isset($_SERVER['HTTP_USER_AGENT']) ? str_replace("\n", ' ', $_SERVER['HTTP_USER_AGENT']) : '')."\n".
			'Peak Memory Usage: '.memory_get_peak_usage().'/'.FileUtil::getMemoryLimit()."\n";
		do {
			$message .= "======\n".
			'Error Class: '.get_class($e)."\n".
			'Error Message: '.str_replace("\n", ' ', $e->getMessage())."\n".
			'Error Code: '.intval($e->getCode())."\n".
			'File: '.str_replace("\n", ' ', $e->getFile()).' ('.$e->getLine().')'."\n".
			'Extra Information: '.($e instanceof IExtraInformationException ? base64_encode(serialize($e->getExtraInformation())) : '-')."\n".
			'Stack Trace: '.base64_encode(serialize(array_map(function ($item) {
				$item['args'] = array_map(function ($item) {
					switch (gettype($item)) {
						case 'object':
							return get_class($item);
						case 'array':
							return array_map(function () {
								return '[redacted]';
							}, $item);
						default:
							return $item;
					}
				}, $item['args']);

				return $item;
			}, sanitizeStacktrace($e, true))))."\n";
		}
		while ($e = $e->getPrevious());

		// calculate Exception-ID
		$exceptionID = sha1($message);
		$entry = "<<<<<<<<".$exceptionID."<<<<\n".$message."<<<<\n\n";

		file_put_contents($logFile, $entry, FILE_APPEND);
		return $exceptionID;
	}

	function printThrowable($e) {
		$exceptionID = logThrowable($e); // TODO
	?><!DOCTYPE html>
	<html>
		<head>
			<?php if (!defined('EXCEPTION_PRIVACY') || EXCEPTION_PRIVACY !== 'private') { ?>
			<title>Fatal Error: <?php echo StringUtil::encodeHTML($e->getMessage()); ?></title>
			<?php } else { ?>
			<title>Fatal Error</title>
			<?php } ?>
			<meta charset="utf-8" />
			<style>
				.exception {
					font-size: 13px !important;
					font-family: 'Trebuchet MS', Arial, sans-serif !important;
					color: #444 !important;
					text-align: left !important;
					border: 1px solid #036 !important;
					border-radius: 7px !important;
					background-color: #eee !important;
					overflow: auto !important;
				}
				.exception h1 {
					font-size: 130% !important;
					font-weight: bold !important;
					line-height: 1.1 !important;
					text-shadow: 0 -1px 0 #003 !important;
					color: #fff !important;
					word-wrap: break-word !important;
					border-bottom: 1px solid #036;
					border-radius: 6px 6px 0 0 !important;
					background-color: #369 !important;
					margin: 0 !important;
					padding: 5px 10px !important;
				}
				.exception div {
					border-top: 1px solid #fff !important;
					border-bottom-right-radius: 6px !important;
					border-bottom-left-radius: 6px !important;
					padding: 0 10px 10px !important;
				}
				.exception p {
					margin: 0 !important;
				}
				.exception h2 {
					font-size: 130% !important;
					font-weight: bold !important;
					color: #369 !important;
					text-shadow: 0 1px 0 #fff !important;
					margin: 5px 0 !important;
				}
				.exception code {
					padding: 0px 3px !important;
					border-radius: 3px !important;
					background-color: white !important;
					border: 1px solid #036 !important;
				}
				.exception .pre {
					white-space: pre !important;
					font-family: monospace; !important;
					text-overflow: ellipsis !important;
					overflow: hidden !important;
				}
				.exception .pre:hover {
					text-overflow: clip !important;
					overflow: auto !important;
				}
				.exception dt {
					float: left !important;
					width: 200px !important;
					text-align: right !important;
					font-weight: bold !important;
				}
				.exception dd {
					margin-left: 210px !important;
				}
				.exception dd::before {
					content: "\FEFF" !important;
				}
				.exception dl {
					margin: 0 !important;
				}
				.exception dl::after {
					clear: both !important;
				}
			</style>
		</head>
		<body>
			<div class="exception">
				<?php if (!defined('EXCEPTION_PRIVACY') || EXCEPTION_PRIVACY !== 'private') { ?>
				<h1>Fatal Error: <?php echo StringUtil::encodeHTML($e->getMessage()); ?></h1>
				<?php } else { ?>
				<h1>Fatal Error <!-- :( --></h1>
				<?php } ?>
				<div>
					<?php
					$message = '
					<h2>What happened?</h2>
					<p>An unrecoverable error occured while trying to handle your request. The internal error code is as follows: <code><?php echo $exceptionID; ?></code></p>
					<p>Please send this code to the administrator to help him fix the issue.</p>
					<p>If you are the administrator you can view the complete error message at "ACP > Logs > Errors" einsehen. The error code itself is worthless for the support!</p>
					';
					try {
						$message = str_replace('{$exceptionID}', $exceptionID, WCF::getLanguage()->get('wcf.global.error.exception', true));
					}
					catch (\Exception $e) {

					}
					catch (\Throwable $e) {

					}
					echo $message;
					?>
				</div>
				<?php if (!defined('EXCEPTION_PRIVACY') || EXCEPTION_PRIVACY !== 'private') { ?>
					<div>
						<h2>System Information</h2>
						<dl>
							<dt>PHP Version</dt> <dd><?php echo StringUtil::encodeHTML(phpversion()); ?></dd>
							<dt>WCF Version</dt> <dd><?php echo StringUtil::encodeHTML(WCF_VERSION); ?></dd>
							<dt>Date</dt> <dd><?php echo gmdate('r'); ?></dd>
							<dt>Request URI</dt> <dd><?php if (isset($_SERVER['REQUEST_URI'])) echo StringUtil::encodeHTML($_SERVER['REQUEST_URI']); ?></dd>
							<dt>Referrer</dt> <dd><?php if (isset($_SERVER['HTTP_REFERER'])) echo StringUtil::encodeHTML($_SERVER['HTTP_REFERER']); ?></dd>
							<dt>User Agent</dt> <dd><?php if (isset($_SERVER['HTTP_USER_AGENT'])) echo StringUtil::encodeHTML($_SERVER['HTTP_USER_AGENT']); ?></dd>
							<dt>Peak Memory Usage</dt> <dd><?php echo $peakMemory = memory_get_peak_usage(); ?>/<?php echo $memoryLimit = FileUtil::getMemoryLimit(); ?> Byte (<?php echo round($peakMemory / 1024 / 1024, 3); ?>/<?php echo round($memoryLimit / 1024 / 1024, 3); ?> MiB)</dd>
						</dl>
					</div>
					<?php
					$first = true;
					do {
					?>
					<div>
						<h2><?php if (!$e->getPrevious() && !$first) { echo "Original "; } else if ($e->getPrevious() && $first) { echo "Final "; } ?>Error</h2>
						<?php if ($e instanceof SystemException && $e->getDescription()) { ?>
							<p><?php echo $e->getDescription(); ?></p>
						<?php } ?>
						<dl>
							<dt>Error Class</dt> <dd><?php echo get_class($e); ?></dd>
							<dt>Error Message</dt> <dd><?php echo StringUtil::encodeHTML($e->getMessage()); ?></dd>
							<?php if ($e->getCode()) { ?><dt>Error Code</dt> <dd><?php echo intval($e->getCode()); ?></dd><?php } ?>
							<dt>File</dt> <dd><?php echo StringUtil::encodeHTML(sanitizePath($e->getFile())); ?> (<?php echo $e->getLine(); ?>)</dd>
							<?php
							if ($e instanceof SystemException) {
								ob_start();
								$e->show();
								ob_end_clean();

								$reflection = new \ReflectionClass($e);
								$property = $reflection->getProperty('information');
								$property->setAccessible(true);
								if ($property->getValue($e)) {
									throw new \Exception("Using the 'information' property of SystemException is not supported any more.");
								}
							}
							if ($e instanceof IExtraInformationException) {
								foreach ($e->getExtraInformation() as list($key, $value)) {
									echo "<dt>".StringUtil::encodeHTML($key)."</dt> <dd>".StringUtil::encodeHTML($value)."</dd>";
								}
							}
							?>
							<dt>Stack Trace</dt>
							<dd class="pre"><?php
								$trace = sanitizeStacktrace($e);
								$pathLength = array_reduce($trace, function ($carry, $item) {
									return max($carry, mb_strlen($item['file'].$item['line']));
								}, 0) + 3;
								for ($i = 0, $max = count($trace); $i < $max; $i++) {
									echo '#'.$i.' '.str_pad(StringUtil::encodeHTML($trace[$i]['file']).' ('.$trace[$i]['line'].')', $pathLength, ' ', STR_PAD_RIGHT).':';
									echo ' '.$trace[$i]['class'].$trace[$i]['type'].$trace[$i]['function'].'(';
									echo implode(', ', array_map(function ($item) {
										switch (gettype($item)) {
											case 'integer':
											case 'double':
												return $item;
											case 'NULL':
												return 'null';
											case 'string':
												return '<span title="'.StringUtil::encodeHTML($item).'">"'.StringUtil::encodeHTML(addcslashes(StringUtil::truncate($item, 25, StringUtil::HELLIP, true), "\n")).'"</span>';
											case 'boolean':
												return $item ? 'true' : 'false';
											case 'array':
												$keys = array_keys($item);
												if (count($keys) > 5) return "[ ".count($keys)." items ]";
												return '[ '.implode(', ', array_map(function ($item) {
													return $item.' => ';
												}, $keys)).']';
											case 'object':
												return get_class($item);
										}
									}, $trace[$i]['args']));
									echo ")\n";
								}
								?></dd>
						</dl>
					</div>
					<?php
					$first = false;
					} while ($e = $e->getPrevious());
					?>
				<?php } ?>
			</div>
		</body>
	</html>
	<?php
	}

	function sanitizeStacktrace($e, $ignorePaths = false) {
		$trace = $e->getTrace();

		return array_map(function ($item) use ($ignorePaths) {
			if (!isset($item['file'])) $item['file'] = '[internal function]';
			if (!isset($item['line'])) $item['line'] = '?';
			if (!isset($item['class'])) $item['class'] = '';
			if (!isset($item['type'])) $item['type'] = '';
			
			// strip database credentials
			if (preg_match('~\\\\?wcf\\\\system\\\\database\\\\[a-zA-Z]*Database~', $item['class']) || $item['class'] === 'PDO') {
				if ($item['function'] === '__construct') {
					$item['args'] = array_map(function () {
						return '[redacted]';
					}, $item['args']);
				}
			}

			if (!$ignorePaths) {
				$item['args'] = array_map(function ($item) {
					if (!is_string($item)) return $item;

					if (preg_match('~^'.preg_quote($_SERVER['DOCUMENT_ROOT'], '~').'~', $item)) {
						$item = sanitizePath($item);
					}

					return preg_replace('~^'.preg_quote(WCF_DIR, '~').'~', '*/', $item);
				}, $item['args']);

				$item['file'] = sanitizePath($item['file']);
			}

			return $item;
		}, $trace);
	}

	function sanitizePath($path) {
		if (WCF::debugModeIsEnabled() && defined('EXCEPTION_PRIVACY') && EXCEPTION_PRIVACY === 'public') {
			return $path;
		}

		return '*/'.FileUtil::removeTrailingSlash(FileUtil::getRelativePath(WCF_DIR, $path));
	}
}
// @codingStandardsIgnoreEnd
