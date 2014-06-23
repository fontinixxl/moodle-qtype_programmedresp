
var callbackResult = false;
var callerelement = false;
var maxconcatnum = 50;
var varmatchpattern = /\{\$[a-zA-Z0-9]*\}/g;
var questiontextregexpfilter = /[^0-9a-z{}\$\ ]/g;
var editing = false;
var opened = false;


function get_questiontext() {
    
    var questiontextvalue = false;
    
    // Search for the question text vars 
    // http://moodle.org/mod/forum/discuss.php?d=16953
    if (window.frames.length > 0) {
        questiontextvalue = frames[0].document.body.innerHTML;        
        
    } else if (document.getElementById("id_questiontext")) {
        questiontextvalue = document.getElementById("id_questiontext").innerHTML;
    }
    
    if (!questiontextvalue) {
    	alert("Ha trobat variables");
        return false;
    }
    
    if (questiontextvalue.match(varmatchpattern) == null) {
        return false;
    }
    
    return questiontextvalue;
}

function get_next_concat_num() {
    
    for (var i = 0; i < maxconcatnum; i++) {
        var tmpconcat = document.getElementById("concatvar_" + i);
        if (!tmpconcat) {
            return i;
        }
    }
    
    return maxconcatnum;
}

function display_vars(element, edit, displayfunctionbutton) {

    callerelement = element;

    if (edit != undefined) {
        editing = edit;
    }
    
    var functionbuttonstr = '';
    if (displayfunctionbutton) {//if is True
        functionbuttonstr = '&displayfunctionbutton=true';
    }
    
    var varsheader = document.getElementById("varsheader");
    varsheader.style.visibility = "visible";
    varsheader.style.display = "inline";
    
    var questiontextvalue = get_questiontext();		//es queda unicament amb el nom de les variables
    
    if (!questiontextvalue) {
        questiontextvalue = '';
    }

    // Stripping garbage, we only want vars as much as we can
    questiontextvalue = questiontextvalue.replace(questiontextregexpfilter, " ");
    display_section("action=displayvars" + functionbuttonstr + "&questiontext=" + questiontextvalue); //params
}

function functionsection_visible() {
    
    var functionheader = document.getElementById("functionheader");
    var fakecaller = document.getElementById("id_programmedrespfid");
    var categoriescaller = document.getElementById("id_functioncategory");
    
    // If it's hidden let's show it
    if (functionheader.style.visibility == "hidden" || functionheader.style.display == "none") {
        functionheader.style.visibility = "visible";
        functionheader.style.display = "inline";
        
        // It's not displayed directly from the form because we must avoid the select element validation
    	display_functionslist(categoriescaller);
        
    // If there is a selected function reload the function arguments
    } else if (fakecaller) {
        display_args(fakecaller);
    }
    
    return false;
}

function display_functionslist(element) {
    
    callerelement = element;
    
    if (opened == true) {
        var functiondiv = window.opener.document.getElementById("id_programmedrespfid_content");
        var category = window.opener.document.getElementById("id_functioncategory");
    } else {
        var functiondiv = document.getElementById("id_programmedrespfid_content");
        var category = document.getElementById("id_functioncategory");
    }
    
    // Hide function data until a new function selection
    functiondiv.style.visibility = "hidden";
    functiondiv.style.display = "none";
    
    return display_section("action=displayfunctionslist&categoryid=" + category.value);
}

function display_args(element) {
    
    callerelement = element;

    var functionid = document.getElementById("id_programmedrespfid").value;

    // Show function div
    var functiondiv = document.getElementById("id_programmedrespfid_content");
    functiondiv.style.visibility = "visible";
    functiondiv.style.display = "inline";
    
    // Concatenated vars
    var concatstring = "";
    var concatnum = get_next_concat_num();
    for (var i = 0; i < concatnum; i++) {

        var concatelement = document.getElementById("concatvar_" + i);
        if (concatelement != false) {
            for (var elementi = 0; elementi < concatelement.options.length; elementi++) {
                if (concatelement.options[elementi].selected) {
                    concatstring += "&concatvar_" + i + "[]=" + concatelement.options[elementi].value;
                }
            }
        }
    }

    
    var questiontextvalue = get_questiontext();
    if (!questiontextvalue) {
        questiontextvalue = '';
    }
    questiontextvalue = questiontextvalue.replace(questiontextregexpfilter, " ");
    
    // function id + question text to extract the vars + the concatenated vars created
    return display_section("action=displayargs&function=" + functionid + "&questiontext=" + questiontextvalue + concatstring);
}


function display_section(params) {
    
    if (opened == true) {
        var contentdiv = window.opener.document.getElementById(callerelement.id + "_content");
    } else {
        var contentdiv = document.getElementById(callerelement.id + "_content");
    }
    contentdiv.innerHTML = "";
    
    // TODO: Posar-li un loading.gif
    
    // Responses manager
    var callbackHandler = 
    {
          success: process_display_section,
          failure: failure_display_section,
          timeout: 50000
    };

	dir = wwwroot+"/question/type/programmedresp/contents.php";
     YUI().use('yui2-connection', function(Y) {
	 Y.YUI2.util.Connect.asyncRequest('POST', dir, callbackHandler, params);
	 });
	 

    
    return callbackResult;
}

function process_display_section(transaction) {

    if (opened == true) {
        var contentdiv = window.opener.document.getElementById(callerelement.id + "_content");
    } else {
        var contentdiv = document.getElementById(callerelement.id + "_content");
    }
    contentdiv.innerHTML = transaction.responseText;
    
    callbackResult = false;
    callerelement = false;
    
    // The editing param will only be true when calling display_vars on edition
    if (editing != false) {
        if (opened == true) {
            var argscaller = window.opener.document.getElementById("id_programmedrespfid");
        } else {
            var argscaller = document.getElementById("id_programmedrespfid");
        }
        display_args(argscaller);
        editing = false;
    }
}

function failure_display_section() {    callbackResult = false;}


function display_responselabels() {

    var responselabelsdiv;
    var responselabelslinkdiv;
    
    if (responselabelsdiv = document.getElementById("id_responseslabels")) {
        responselabelsdiv.style.visibility = "visible";
        responselabelsdiv.style.display = "inline";
    }
    
    if (responselabelslinkdiv = document.getElementById("id_responselabelslink")) {
        responselabelslinkdiv.style.visibility = "hidden";
        responselabelslinkdiv.style.display = "none";
    }
    
    return false;
}


function add_concat_var() {
    
    // The element number to add
    var concatnum = get_next_concat_num();
    
    // The questiontext vars
    var varsstring = '';
    var vars = get_questiontext_vars();
    for (var vari = 0; vari < vars.length; vari++) {
        varsstring += "&vars[]=" + vars[vari];
    }
    
    // Responses manager
    var callbackHandler = 
    {
          success: process_add_concat_var,
          failure: failure_add_concat_var,
          timeout: 50000
    };
    
    var params = "action=addconcatvar&concatnum=" + concatnum + varsstring;
    
    //YAHOO.util.Connect.asyncRequest("POST", wwwroot + "/question/type/programmedresp/contents.php", callbackHandler, params);
    dir = wwwroot+"/question/type/programmedresp/contents.php";
     YUI().use('yui2-connection', function(Y) {
	 Y.YUI2.util.Connect.asyncRequest('POST', dir, callbackHandler, params);
	 });
    
    return callbackResult;
}

function process_add_concat_var(transaction) {
    
    var maindiv = document.getElementById("id_concatvars");
    var vardiv = document.createElement("div");
    
    vardiv.innerHTML = transaction.responseText;
    maindiv.appendChild(vardiv);
}

function failure_add_concat_var() {
    
}

function get_questiontext_vars() {

    var questiontextvalue = get_questiontext();
    
    var matches = questiontextvalue.match(varmatchpattern);
    if (matches == null) {
        return false;
    }
    
    var returnarray = new Array();
    for (var i = 0; i < matches.length; i++) {
        returnarray.push(matches[i].substr(2, (matches[i].length - 3)));
    }
    
    return returnarray;
}

function confirm_concat_var(concatid) {
    
}

function cancel_concat_var(concatid) {

    var concatelement = document.getElementById(concatid);
    
    for (var i = 0; i < concatelement.options.length; i++) {
        if (concatelement.options[i].selected) {
            concatelement.options[i].selected = false;
        }
    }
    
    return false;
}

function change_argument_type(element, argumentkey) {

    var types = new Array('fixed', 'variable', 'guidedquiz', 'concat');
    var tmpelement;
    
    for (var i = 0; i < types.length; i++) {
        
        tmpelement = document.getElementById("id_argument_" + types[i] + "_" + argumentkey);

        if (element.value == i) {
            tmpelement.style.visibility = "visible";
            tmpelement.style.display = "inline";
        } else {
            tmpelement.style.visibility = "hidden";
            tmpelement.style.display = "none";
        }    
    }
}

// TODO: Add a check_maximum and check_minimum to ensure max > min

function check_numeric(element, message) {
    
    var value = element.value;
    var regex = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;
    
    if (value == '' || !regex.test(value)) {
        return qf_errorHandler(element, message);
    } else {
        return qf_errorHandler(element, '');
    }
}


function add_to_parent(id, name, openerelementid, afterkey) {
    
    var openerselect = window.opener.document.getElementById(openerelementid);
    
    var optionslength = openerselect.options.length;
    
    var newoption = document.createElement('option');
    newoption.value = id;
    newoption.text = name;
    
    // If it's a function
    if (afterkey == undefined) {
        openerselect.options[optionslength] = newoption;
        return true;
    }
    
    // Store an identation char
    // The dirties hack I've ever seen
    var rootidentchar = openerselect.options[0].text.substr(0, 1);
    
    // After the parent option
    for (var i = 0; i < openerselect.options.length; i++) {
        
        // Move one position down each option
        if (openerselect.options[i].value == afterkey) {
            
            // Getting and adding to the new option the parent identation
            var identations = '';
            
            // If his parent is the root nothing
            if (afterkey == 0) {
                
            // If it's a root child two
            } else if (openerselect.options[i].text[0] != rootidentchar) {
                identations = rootidentchar + rootidentchar;
                
            // Any other case iterate
            } else {
                while (openerselect.options[i].text.indexOf(identations) != -1) {
                    identations = identations + openerselect.options[i].text.substr(0, 2);
                }
            }
            newoption.text = identations + newoption.text;
            
            // Add the child option and move the others
            var tmpoption = false;
            var nextoption = openerselect.options[i + 1];
            openerselect.options[i + 1] = newoption;
                                 
            for (var j = (i + 2); j <= openerselect.options.length; j++) {
                tmpoption = openerselect.options[j];
                openerselect.options[j] = nextoption;
                nextoption = tmpoption;
            }
            
            // Selecting the new option
            openerselect.selectedIndex = (i + 1);
            
            return true;
        }
    }
}


function update_addfunctionurl() {
    
    if (opened == true) {
        
        var categoryelement = window.opener.document.getElementById("id_functioncategory");
        var functionelement = window.opener.document.getElementById("id_addfunctionurl");
    } else {
        var categoryelement = document.getElementById("id_functioncategory");
        var functionelement = document.getElementById("id_addfunctionurl");
    }
    
    // If there is no function edition capability
    if (!functionelement) {
        return true;
    }
    
    // Add the index
    var fcatidindex = functionelement.href.indexOf("&fcatid", 0);
    if (fcatidindex == -1) {
        functionelement.href = functionelement.href + "&fcatid=" + categoryelement.value;
    } else {
        functionelement.href = functionelement.href.substr(0, fcatidindex) + "&fcatid=" + categoryelement.value;
    }
    
}


//funcions necessaries per fer crides a funcions javascript des de PHP
function exec_js_manage(unused_yui, id, name, openerelementid, parent){
    
    add_to_parent(id, name, openerelementid, parent);
    opened = true;
    update_addfunctionurl();
    window.close();
    
}

function exec_js_funct_manage(unused_yui, id, name, openerelementid){
    
    add_to_parent(id, name, openerelementid);
}