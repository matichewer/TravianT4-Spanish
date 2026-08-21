<?php
?>
<div class="reportsPerPage">
	<label for="reportsPerPage">Informes por página:</label>
	<select id="reportsPerPage" onchange="window.location.href=this.value;">
		<?php foreach($reportPageSizes as $reportPageSize) {
			$reportsPerPageQuery = array('per_page' => $reportPageSize);
			if($reportFilter > 0) {
				$reportsPerPageQuery['t'] = $reportFilter;
			}
			$reportsPerPageUrl = 'berichte.php?'.http_build_query($reportsPerPageQuery);
		?>
		<option value="<?php echo htmlspecialchars($reportsPerPageUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php if($reportPageSize === $reportsPerPage) { ?> selected="selected"<?php } ?>><?php echo $reportPageSize; ?></option>
		<?php } ?>
	</select>
</div>
