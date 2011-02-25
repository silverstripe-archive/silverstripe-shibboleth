jQuery(function(){
AjaxMemberLookup.afterAutocomplete = function(field, selectedItem) {
	var data = selectedItem.getElementsByTagName('span')[1].innerHTML;
	var items = data.split(",");
	form = Element.ancestorOfType(field, 'form');
	// TODO more flexible column-detection
	form.elements.FirstName.value = items[0];
	form.elements.Surname.value = items[1];
	form.elements.Email.value = items[2];
	form.elements.UniqueIdentifier.value = items[2];
	if(items[3] && form.elements.Password)
		form.elements.Password.value = items[3];
};
});
