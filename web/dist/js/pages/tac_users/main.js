checkConfiguration()
getUserInfo()
/////DISABLED, ENABLED SWITCHER///START//
function disabledSwitcher(form,action)
{
	var button = $('form#'+form+'UserForm div.disabled button')
	var input = $('form#'+form+'UserForm input[name="disabled"]')
	
	if (action == undefined)
	{
		
		if (input.val() == '0'){
			button.removeClass('btn-success').addClass('btn-warning').text('Disabled');
			input.val('1')
		}
		else if (input.val() == '1'){
			button.removeClass('btn-warning').addClass('btn-success').text('Enabled');
			input.val('0')
		}
		return;
	}
	
	if (action == 1){button.removeClass('btn-success').addClass('btn-warning').text('Disabled')}
	if (action == 0){button.removeClass('btn-warning').addClass('btn-success').text('Enabled')}
	input.val(action)
	
}
/////DISABLED, ENABLED SWITCHER///END//
///////////////////////////////////////
///////////////////////////////////////
//////ACTIVATE SELECT2/////
//////TEMPLATE FUNCTIONS/////
function selectionTemplate(data){
	var default_flag_class = (data.default_flag) ? 'option_default_flag': ''
	var output='<div class="selectGroupOption '+ default_flag_class +'">';
		output += '<text>'+data.text+'</text>';
		output += '<specialFlags>';
		output += (data.key) ? '<small class="label pull-right bg-green" style="margin:3px">k</small>' : '';
		output += (data.enable) ? ' <small class="label pull-right bg-yellow" style="margin:3px">e</small>' : '';
		output += (data.default_flag) ? ' <small class="label pull-right bg-gray" style="margin:3px">d</small>' : '';
		output += '</specialFlags>'
	output += '</div>'
	return output;
}
function resultTemplate(data){
	console.log(data)
	return 222;
}
////////////////////////////
var select_group_add = $('#addUserForm .select_group')
var select_group_edit = $('#editUserForm .select_group')
var generalSelect2Data = {
	ajax:{
		url: API_LINK+"tacacs/user/group/list/",
		dataType: 'json',
		processResults: function (data) {
			// Tranforms the top-level key of the response object from 'items' to 'results'
			console.log(data)
			return {
				results: data.items
			};
		},
		result: function(data){
		console.log(data)
		}
	},
	escapeMarkup: function(markup){ return markup;},
	templateResult: selectionTemplate,
	templateSelection: selectionTemplate,
	minimumResultsForSearch: Infinity,
}
select_group_add.select2(generalSelect2Data)
select_group_edit.select2(generalSelect2Data)
function preSelection(groupId, selector)
{
	if (groupId == 0)
	{
		var output='<div class="selectGroupOption">';
				output += '<text>None</text>';
				output += '</div>'
			var option = new Option(output, 0, true, true)
			if (selector == 'addModal') select_group_add.append(option).trigger('change');
			if (selector == 'editModal') select_group_edit.append(option).trigger('change');
		return;
	}
	var data = {
		"action": "GET",
		"groupId": groupId,
		"test" : "none"
		};	
	$.ajax({
		type: "GET",
		dataType: "json",
		url: API_LINK+"tacacs/user/group/list/",
		cache: false,
		data: data,
		success: function(data) {
			console.log(data);
			var output='<div class="selectGroupOption">';
				output += '<text>'+data.item.text+'</text>';
				output += '<specialFlags>';
				output += (data.item.key) ? '<small class="label pull-right bg-green" style="margin:3px">k</small>' : '';
				output += (data.item.enable) ? ' <small class="label pull-right bg-yellow" style="margin:3px">e</small>' : '';
				output += '</specialFlags>'
				output += '</div>'
			var option = new Option(output, data.item.id, true, true)
			if (selector == 'addModal') select_group_add.append(option).trigger('change');
			if (selector == 'editModal') select_group_edit.append(option).trigger('change');
			
		},
		error: function(data) {
			//console.log(data);
			errorHere(data);
		}
	});
}
$('#addUser').on('show.bs.modal', function(){
	preSelection(0, 'addModal');
})
///////////////////////////////////////
/////CHECKBOX ENABLING///
var generalCheckboxParameters={
	checkboxClass: 'icheckbox_square-blue',
	radioClass: 'iradio_square-blue',
	increaseArea: '20%' // optional
}
$('.checkbox.icheck').iCheck(generalCheckboxParameters);
////////////////////////////////
//////SELECT CHANGE FOR LOGIN////////
$('form#addUserForm select[name="login_flag"]').change(function(){
	console.log($('form#addUserForm select[name="login_flag"]').val())
	if ($('form#addUserForm select[name="login_flag"]').val() == 1 || $('form#addUserForm select[name="login_flag"]').val() == 2){
		$('form#addUserForm div.login_encrypt_section').show()
	} else {
		$('form#addUserForm div.login_encrypt_section').hide()
	}
})
$('form#editUserForm select[name="login_flag"]').change(function(){
	console.log($('form#editUserForm select[name="login_flag"]').val())
	if ($('form#editUserForm select[name="login_flag"]').val() == 1 || $('form#editUserForm select[name="login_flag"]').val() == 2){
		$('form#editUserForm div.login_encrypt_section').show()
	} else {
		$('form#editUserForm div.login_encrypt_section').hide()
	}
})
///////////////////////////////////
//////SELECT CHANGE FOR ENABLE////////
$('form#addUserForm select[name="enable_flag"]').change(function(){
	console.log($('form#addUserForm select[name="enable_flag"]').val())
	if ($('form#addUserForm select[name="enable_flag"]').val() == 1 || $('form#addUserForm select[name="enable_flag"]').val() == 2){
		$('form#addUserForm div.enable_encrypt_section').show()
	} else {
		$('form#addUserForm div.enable_encrypt_section').hide()
	}
})
$('form#editUserForm select[name="enable_flag"]').change(function(){
	console.log($('form#editUserForm select[name="enable_flag"]').val())
	if ($('form#editUserForm select[name="enable_flag"]').val() == 1 || $('form#editUserForm select[name="enable_flag"]').val() == 2){
		$('form#editUserForm div.enable_encrypt_section').show()
	} else {
		$('form#editUserForm div.enable_encrypt_section').hide()
	}
})
///////////////////////////
////////////////////////////////
////ADD USER FUNCTION///START//
function addUser(){
	console.log('Adding new tacacs user');
	$('.form-group.has-error').removeClass('has-error');
	$('p.text-red').remove();
	$('p.help-block').show();
	/////////ADD NEW DEVICE///START//
	var data = {
		"action": "POST",
		"username": $('form#addUserForm input[name="username"]').val(),
		"login": $('form#addUserForm input[name="login"]').val(),
		"login_flag": $('form#addUserForm select[name="login_flag"]').val(),
		"login_encrypt": $('form#addUserForm input[name="login_encrypt"]').prop('checked'),
		"enable": $('form#addUserForm input[name="enable"]').val(),
		"enable_flag": $('form#addUserForm select[name="enable_flag"]').val(),
		"enable_encrypt": $('form#addUserForm input[name="enable_encrypt"]').prop('checked'),
		"group": select_group_add.select2('data')[0].id,
		"default_service": $('form#addUserForm input[name="default_service"]').prop('checked'),
		"message": $('form#addUserForm textarea[name="message"]').val(),
		"manual": $('form#addUserForm textarea[name="manual"]').val(),
		"test" : "none"
		};	
	$.ajax({
		type: "POST",
		dataType: "json",
		url: API_LINK+"tacacs/user/add/",
		cache: false,
		data: data,
		success: function(data) {
			console.log(data);
			if (data['error']['status']){
				//console.log(data['error']['validation'])
				for (v in data['error']['validation']){
					//console.log(v)
					if (!(data['error']['validation'][v] == null)){
						//console.log($('form#addUserForm div.'+v))
						$('form#addUserForm div.'+v).addClass('has-error')
						$('div.form-group.'+v+' p.help-block').hide()
						var error_message='';
						for (num in data['error']['validation'][v]){
							error_message='<p class="text-red">'+data['error']['validation'][v][num]+'</p>';
						}
						$('div.form-group.'+v).append(error_message)
					}
				}
				return;
			}
			toastr["success"]("User "+ $('form#addUserForm input[name="username"]').val() +" was added")
			$("#addUser").modal("hide");
			changeApplyStatus(data['changeConfiguration'])
			clearAddUserModal();
			setTimeout( function () {dataTable.ajax.reload()}, 2000 );
		},
		error: function(data) {
			//console.log(data);
			errorHere(data);
		}
	});
	
}
///////////////////////////
function clearAddUserModal(){
	$('form#addUserForm input[name="username"]').val('')
	$('form#addUserForm input[name="login"]').val('')
	$('form#addUserForm input[name="enable"]').val('')
	$('form#addUserForm input[name="group"]').val('')
	$('form#addUserForm div.enable_encrypt_section').show()
	$('form#addUserForm div.login_encrypt_section').show()
	$('form#addUserForm input[name="login_encrypt"]').iCheck('check')
	
	$('form#addUserForm select[name="login_flag"] option[value="1').prop('selected', true)
			
	$('form#addUserForm select[name="enable_flag"] option[value="1"]').prop('selected', true)
	
	$('form#addUserForm input[name="enable_encrypt"]').iCheck('check')
	$('form#addUserForm input[name="default_service"]').iCheck('check')
	$('form#addUserForm textarea[name="message"]').val('')
	$('form#addUserForm textarea[name="manual"]').val('')
	$('.form-group.has-error').removeClass('has-error');
	
	disabledSwitcher('add','0')
	
	$('a.manualConfTrigger').show()
	$('div.manualConfiguration').hide()
	
	$('p.text-red').remove();
	$('p.help-block').show();
}
////ADD USER FUNCTION///END//
/////////////////////////////////////////////
////EDIT USER FUNCTION///START//
function editUser(id,username){ //GET INFO ABOUT USER//
	var data = {
		"action": "GET",
		"username": username,
		"id": id,
		"test" : "none"
	};
	$.ajax({
		type: "GET",
		dataType: "json",
		url: API_LINK+"tacacs/user/edit/",
		cache: false,
		data: data,
		success: function(data) {
			console.log(data);
			$('username.text-green').text(data['user']['username'])
			$('form#editUserForm input[name="username"]').val(data['user']['username'])
			$('form#editUserForm input[name="username_old"]').val(data['user']['username'])
			$('form#editUserForm input[name="login"]').val(data['user']['login'])
			$('form#editUserForm input[name="enable"]').val(data['user']['enable'])
			$('form#editUserForm input[name="id"]').val(data['user']['id'])
			$('form#editUserForm input[name="group"]').val(data['user']['group'])
			
			var enable_encryption = (data['user']['enable_flag'] == 1 || data['user']['enable_flag'] == 2) ? 'uncheck' : 'check';
			$('form#editUserForm input[name="enable_encrypt"]').iCheck(enable_encryption)
			if (enable_encryption == 'check') {$('form#editUserForm div.enable_encrypt_section').hide()}
			else ($('form#editUserForm div.enable_encrypt_section').show())
			
			var login_encryption = (data['user']['login_flag'] == 1 || data['user']['login_flag'] == 2) ? 'uncheck' : 'check';
			$('form#editUserForm input[name="login_encrypt"]').iCheck(login_encryption)
			if (login_encryption == 'check') {$('form#editUserForm div.login_encrypt_section').hide()}
			else ($('form#editUserForm div.login_encrypt_section').show())
			
			$('form#editUserForm select[name="login_flag"] option[value="'+data['user']['login_flag']+'"]').prop('selected', true)
			
			$('form#editUserForm select[name="enable_flag"] option[value="'+data['user']['enable_flag']+'"]').prop('selected', true)
			
			disabledSwitcher('edit',data['user']['disabled'])
			
			preSelection(data['user']['group'], 'editModal');
			
			var default_service = (data['user']['default_service'] == 1) ? 'check' : 'uncheck';
			$('form#editUserForm input[name="default_service"]').iCheck(default_service)
			
			$('form#editUserForm textarea[name="message"]').val(data['user']['message'])
			$('form#editUserForm textarea[name="manual"]').val(data['user']['manual'])
			
			$('text.created_at').text('Created at '+data['user']['created_at']);
			$('text.updated_at').text('Last update was at '+data['user']['updated_at']);
			$('#editUser').modal('show')
		},
		error: function(data) {
			//console.log(data);
			errorHere(data);
		}
	});
}
function submitUserChanges(){
	$('.form-group.has-error').removeClass('has-error');
	$('p.text-red').remove();
	$('p.help-block').show();
		var data = {
		"action": "POST",
		"username": $('form#editUserForm input[name="username"]').val(),
		"username_old": $('form#editUserForm input[name="username_old"]').val(),
		"login": $('form#editUserForm input[name="login"]').val(),
		"login_flag": $('form#editUserForm select[name="login_flag"]').val(),
		"login_encrypt": $('form#editUserForm input[name="login_encrypt"]').prop('checked'),
		"enable": $('form#editUserForm input[name="enable"]').val(),
		"enable_flag": $('form#editUserForm select[name="enable_flag"]').val(),
		"enable_encrypt": $('form#editUserForm input[name="enable_encrypt"]').prop('checked'),
		"group": select_group_edit.select2('data')[0].id,
		"id": $('form#editUserForm input[name="id"]').val(),
		"default_service": $('form#editUserForm input[name="default_service"]').prop('checked'),
		"message": $('form#editUserForm textarea[name="message"]').val(),
		"manual": $('form#editUserForm textarea[name="manual"]').val(),
		"test" : "none"
	};
	$.ajax({
		type: "POST",
		dataType: "json",
		url: API_LINK+"tacacs/user/edit/",
		cache: false,
		data: data,
		success: function(data) {
			console.log(data);
			if (data['error']['status']){
				//console.log(data['error']['validation'])
				for (v in data['error']['validation']){
					//console.log(v)
					if (!(data['error']['validation'][v] == null)){
						//console.log($('form#editUserForm div.'+v))
						$('form#editUserForm div.'+v).addClass('has-error')
						$('div.form-group.'+v+' p.help-block').hide()
						var error_message='';
						for (num in data['error']['validation'][v]){
							error_message='<p class="text-red">'+data['error']['validation'][v][num]+'</p>';
						}
						$('div.form-group.'+v).append(error_message)
					}
				}
				return;
			}
			toastr["success"]("User "+ $('form#editUserForm input[name="Username"]').val() +" was updated")
			$("#editUser").modal("hide");
			changeApplyStatus(data['changeConfiguration'])
			clearEditUserModal();
			setTimeout( function () {dataTable.ajax.reload()}, 2000 );
		},
		error: function(data) {
			//console.log(data);
			errorHere(data);
		}
	});
}
///////////////////////////
function clearEditUserModal(){
	$('form#editUserForm input[name="username"]').val('')
	$('form#editUserForm input[name="login"]').val('')
	$('form#editUserForm input[name="enable"]').val('')
	$('form#editUserForm input[name="group"]').val('')
	$('form#editUserForm div.enable_encrypt_section').show()
	$('form#editUserForm div.login_encrypt_section').show()
	$('form#editUserForm input[name="login_encrypt"]').iCheck('check')
	$('form#editUserForm input[name="enable_encrypt"]').iCheck('check')
	$('form#editUserForm input[name="default_service"]').iCheck('check')
	$('form#editUserForm textarea[name="message"]').val('')
	$('form#editUserForm input[name="Surname"]').val('')
	$('form#editUserForm textarea[name="manual"]').val('')
	$('.form-group.has-error').removeClass('has-error');
	
	$('a.manualConfTrigger').show()
	$('div.manualConfiguration').hide()	
	
	$('p.text-red').remove();
	$('p.help-block').show();
}
////EDIT USER FUNCTION///END//
//////////////////////////////
////DELETE USER FUNCTION////START//
function deleteUser(id,username){
	console.log('Deleting UserID:'+id+' with username '+username)
	if (confirm("Do you want delete '"+username+"'?")){
		/////////DELETE USER///START//
		var data = {
			"action": "POST",
			"username": username,
			"id": id,
			"test" : "none"
			};	
		$.ajax({
			type: "POST",
			dataType: "json",
			url: API_LINK+"tacacs/user/delete/",
			cache: false,
			data: data,
			success: function(data) {
				console.log(data);
				if(data['deleteUser']!=1){toastr["error"]("Oops! Unknown error appeared :(");return;}
				toastr["success"]("User "+ username +" was deleted")
				changeApplyStatus(data['changeConfiguration'])				
				setTimeout( function () {dataTable.ajax.reload()}, 2000 );
			},
			error: function(data) {
				//console.log(data);
				errorHere(data);
			}
		});
		/////////DELETE USER///END////
	}
	return;
}
////DELETE USER FUNCTION////END//
/////////////////////////////////

////CLEAR MODALS FUNCTIONS//////
$('#addUser').on('hidden.bs.modal', function(){
	clearAddUserModal()
})
$('#editUser').on('hidden.bs.modal', function(){
	clearEditUserModal()
})
////////////////////////////////
////////////////////////////////
////MANUAL CONFIGURATION TRIGGER//START//
$('a.manualConfTrigger').click(function(){
	$('a.manualConfTrigger').hide()
	$('div.manualConfiguration').show()
})
////MANUAL CONFIGURATION TRIGGER//END//
////////////////////////////////////////