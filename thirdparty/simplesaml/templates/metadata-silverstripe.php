<?php
$this->data['header'] = $this->t('metadata_' . $this->data['header']);
?>

		<h2><?php echo($this->t('metadata_metadata')); ?></h2>
		
		<p><?php echo($this->t('metadata_xmlformat')); ?></p>
		
		<pre><?php echo $this->data['metadata']; ?>
</pre>
		
		
		<p><?php echo($this->t('metadata_simplesamlformat')); ?></p>
		
		<pre><?php echo $this->data['metadataflat']; ?>
</pre>
		
		

		


<?php $this->includeAtTemplateBase('includes/footer.php'); ?>