<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if (!$modx->hasPermission('save_template')) {
    $e->setError(3);
    $e->dumpError();
}

if(isset($_POST['id']) && preg_match('@^[0-9]+$@',$_POST['id'])) $id = $_POST['id'];
$name = $modx->db->escape(trim($_POST['name']));
$description = $modx->db->escape($_POST['description']);
$caption = $modx->db->escape($_POST['caption']);
$type = $modx->db->escape($_POST['type']);
$elements = $modx->db->escape($_POST['elements']);
$default_text = $modx->db->escape($_POST['default_text']);
$rank = isset($_POST['rank']) ? $modx->db->escape($_POST['rank']) : 0;
$display = $modx->db->escape($_POST['display']);
$display_params = $modx->db->escape($_POST['params']);
$locked = $_POST['locked'] == 'on' ? 1 : 0;

//Kyle Jaebker - added category support
if (empty($_POST['newcategory']) && $_POST['categoryid'] > 0) {
    $category = $modx->db->escape($_POST['categoryid']);
} elseif (empty($_POST['newcategory']) && $_POST['categoryid'] <= 0) {
    $category = 0;
} else {
    $catCheck = $modx->manager->checkCategory($modx->db->escape($_POST['newcategory']));
    if ($catCheck) $category = $catCheck;
    else           $category = $modx->manager->newCategory($_POST['newcategory']);
}

if ($name == '')    $name = 'Untitled variable';
if ($caption == '') $caption = $name;
switch ($_POST['mode']) {
    case '300':
        // invoke OnBeforeTVFormSave event
        $tmp = array(
          'mode' => 'new',
          'id'=>''
        );
        $modx->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($name) !== false) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $name);
            $modx->manager->saveFormValues(300);
            $modx->webAlertAndQuit($msg, 'index.php?a=300');
            exit;
        }
        if (check_reserved_names($name) !== false) {
            $msg = sprintf($_lang['reserved_name_warning'], $name);
            $modx->manager->saveFormValues(300);
            $modx->webAlertAndQuit($msg, 'index.php?a=300');
            exit;
        }

        // Add new TV
        $field = compact(explode(',', 'name,description,caption,type,elements,default_text,display,display_params,rank,locked,category'));
        $newid = $modx->db->insert($field, '[+prefix+]site_tmplvars');
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // save access permissions
        saveTemplateAccess();
        saveDocumentAccessPermissons();

        // invoke OnTVFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        $modx->invokeEvent('OnTVFormSave', $tmp);

        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        if (isset($_POST['stay']) && $_POST['stay'] != '') {
        	switch($_POST['stay'])
        	{
        		case '1': $a = '300'             ;break;
        		case '2': $a = "301&id={$newid}" ;break;
        	}
            $url = "index.php?a={$a}&stay={$_POST['stay']}";
        } else {
            $url = "index.php?a=76";
        }
        header("Location: {$url}");
        break;
    case '301':
        // invoke OnBeforeTVFormSave event
        $tmp = array(
          'mode' => 'upd',
          'id' => $id
        );
        $modx->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($name) !== false) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $name);
            $modx->manager->saveFormValues(301);
            $modx->webAlertAndQuit($msg, "index.php?id={$id}&a=301");
            exit;
        }
        if (check_reserved_names($name) !== false) {
            $msg = sprintf($_lang['reserved_name_warning'], $name);
            $modx->manager->saveFormValues(301);
            $modx->webAlertAndQuit($msg, "index.php?id={$id}&a=301");
            exit;
        }
        // update TV
        $was_name = $modx->db->getValue($modx->db->select('name', '[+prefix+]site_tmplvars', "id='{$id}'"));
        $field = compact(explode(',', 'name,description,caption,type,elements,default_text,display,display_params,rank,locked,category'));
        $rs = $modx->db->update($field, '[+prefix+]site_tmplvars', "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited variable not saved!";
        } else {
            $name = stripslashes($name);
            $name = str_replace("'", "''", $name);
            $was_name = str_replace("'", "''", $was_name);
            if ($name !== $was_name) {
                $modx->db->update("content=REPLACE(content,'[*{$was_name}*]','[*{$name}*]')", '[+prefix+]site_content');
                $modx->db->update("content=REPLACE(content,'[*{$was_name}*]','[*{$name}*]')", '[+prefix+]site_templates');
                $modx->db->update("snippet=REPLACE(snippet,'[*{$was_name}*]','[*{$name}*]')", '[+prefix+]site_htmlsnippets');
                $modx->db->update("value=REPLACE(value,    '[*{$was_name}*]','[*{$name}*]')", '[+prefix+]site_tmplvar_contentvalues');
                $modx->db->update("content=REPLACE(content,'[*{$was_name}:','[*{$name}:')", '[+prefix+]site_content');
                $modx->db->update("content=REPLACE(content,'[*{$was_name}:','[*{$name}:')", '[+prefix+]site_templates');
                $modx->db->update("snippet=REPLACE(snippet,'[*{$was_name}:','[*{$name}:')", '[+prefix+]site_htmlsnippets');
                $modx->db->update("value=REPLACE(value,    '[*{$was_name}:','[*{$name}:')", '[+prefix+]site_tmplvar_contentvalues');
            }
            // save access permissions
            saveTemplateAccess();
            saveDocumentAccessPermissons();
            // invoke OnTVFormSave event
            $tmp = array(
                'mode' => 'upd',
                'id' => $id
            );
            $modx->invokeEvent('OnTVFormSave', $tmp);
            // empty cache
            $modx->clearCache(); // first empty the cache
            // finished emptying cache - redirect
            if (isset($_POST['stay']) && $_POST['stay'] != '') {
            	switch($_POST['stay'])
            	{
            		case '1': $a = '300'             ;break;
            		case '2': $a = "301&id={$id}" ;break;
            	}
                $url = "index.php?a={$a}&stay={$_POST['stay']}";
            } else {
                $url = 'index.php?a=76';
            }
            header("Location: {$url}");
        }
        break;
    default:
        echo 'Erm... You supposed to be here now?';
}

function saveTemplateAccess() {
    global $id, $newid;
    global $modx;

    if ($newid)
        $id = $newid;

    $getRankArray = array();

    $getRank = $modx->db->select('templateid,rank', '[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");

    while ($row = $modx->db->getRow($getRank)) {
        $getRankArray[$row['templateid']] = $row['rank'];
    }
    $modx->db->delete('[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");
    
    // update template selections
    $templates = $modx->input_post('template'); // get muli-templates based on S.BRENNAN mod
    if(!$templates) {
        return;
    }
    $total = count($templates);
    for ($i = 0; $i < $total; $i++) {
        $setRank = ($getRankArray[$templates[$i]]) ? $getRankArray[$templates[$i]] : 0;
        $field = array();
        $field['tmplvarid'] = $id;
        $field['templateid'] = $templates[$i];
        $field['rank'] = $setRank;
        $modx->db->insert($field, '[+prefix+]site_tmplvar_templates');
    }
}

function saveDocumentAccessPermissons() {
    global $modx, $id, $newid;

    if ($newid)
        $id = $newid;
    $docgroups = $_POST['docgroups'];

    // check for permission update access
    if ($modx->config['use_udperms'] == 1) {
        // delete old permissions on the tv
        $rs = $modx->db->delete('[+prefix+]site_tmplvar_access', "tmplvarid='{$id}'");
        if (!$rs) {
            echo 'An error occurred while attempting to delete previous template variable access permission entries.';
            exit;
        }
        if (is_array($docgroups)) {
            foreach ($docgroups as $dgkey => $value) {
                $field['tmplvarid'] = $id;
                $field['documentgroup'] = stripslashes($value);
                $rs = $modx->db->insert($field, '[+prefix+]site_tmplvar_access');
                if (!$rs) {
                    echo "An error occured while attempting to save template variable acess permissions.";
                    exit;
                }
            }
        }
    }
}

function check_exist_name($name) { // disallow duplicate names for new tvs
    global $modx;
    $where = "name='{$name}'";
    if ($_POST['mode'] == 301) {
        $where = $where . " AND id!={$_POST['id']}";
    }
    $rs = $modx->db->select('COUNT(id)', '[+prefix+]site_tmplvars', $where);
    $count = $modx->db->getValue($rs);
    if ($count > 0)
        return true;
    else
        return false;
}

function check_reserved_names($name) { // disallow reserved names
    global $modx;

    $reserved_names = explode(',', 'id,type,contentType,pagetitle,longtitle,description,alias,link_attributes,published,pub_date,unpub_date,parent,isfolder,introtext,content,richtext,template,menuindex,searchable,cacheable,createdby,createdon,editedby,editedon,deleted,deletedon,deletedby,publishedon,publishedby,menutitle,donthit,haskeywords,hasmetatags,privateweb,privatemgr,content_dispo,hidemenu');
    if (in_array($name, $reserved_names)) {
        $_POST['name'] = '';
        return true;
    }
    else
        return false;
}
