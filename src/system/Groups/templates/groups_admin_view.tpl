{ajaxheader modname=Groups filename=groups.js}
{gt text="Groups list" assign=templatetitle}
{include file="groups_admin_menu.tpl"}

<div class="z-admincontainer">
    <div class="z-adminpageicon">{img modname=core src=windowlist.gif set=icons/large alt=$templatetitle}</div>
    <h2>{$templatetitle}</h2>

    <a id="appendajax" onclick="groupappend();" style="margin-bottom: 1em;" class="z-floatleft z-icon-es-new z-hide" title="{gt text="Create new group"}" href="javascript:void(0);">{gt text="Create new group"}</a>

    {* general use authid *}
    <input type="hidden" id="groupsauthid" name="authid" value="{insert name="generateauthkey" module="Groups"}" />

    <div class="groupbox z-clearer">
        <ol id="grouplist" class="z-itemlist">
            <li class="z-itemheader z-clearfix">
                <span class="z-itemcell z-w05">{gt text="Internal ID"}</span>
                <span class="z-itemcell z-w15">{gt text="Name"}</span>
                <span class="z-itemcell z-w10">{gt text="Type"}</span>
                <span class="z-itemcell z-w30">{gt text="Description"}</span>
                <span class="z-itemcell z-w10">{gt text="State"}</span>
                <span class="z-itemcell z-w10 z-center">{gt text="Members"}</span>
                <span class="z-itemcell z-w10 z-center">{gt text="Maximum membership"}</span>
                <span class="z-itemcell z-w10">{gt text="Actions"}</span>
            </li>
            {foreach item='group' from=$groups}
            <li id="group_{$group.gid}" class="{cycle values='z-odd,z-even'} z-clearfix">
                <div id="groupcontent_{$group.gid}">
                    <input type="hidden" id="gtypeid_{$group.gid}" value="{$group.gtype}" />
                    <input type="hidden" id="stateid_{$group.gid}" value="{$group.state}" />
                    <input type="hidden" id="modifystatus_{$group.gid}" value="0" />
                    <span id="groupgid_{$group.gid}" class="z-itemcell z-w05">
                        {$group.gid|safetext}
                    </span>
                    <span id="groupname_{$group.gid}" class="z-itemcell z-w15">
                        {$group.name|safetext} {if $group.gid eq $defaultgroup} (*){/if}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupname_{$group.gid}" class="z-itemcell z-w15 z-hide">
                        <input type="text" id="name_{$group.gid}" name="name_{$group.gid}" value="{$group.name|safetext}" size="15" />
                    </span>
                    {* *}
                    <span id="groupgtype_{$group.gid}" class="z-itemcell z-w10">
                        {gt text="$group.gtypelbl|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupgtype_{$group.gid}" class="z-itemcell z-w10 z-hide">
                        <select id="gtype_{$group.gid}" name="gtype_{$group.gid}">
                            {*html_options options=$grouptype selected=$gtype*}
                            {html_options options=$grouptypes}
                        </select>
                    </span>
                    {* *}
                    <span id="groupdescription_{$group.gid}" class="z-itemcell z-w30">
                        {$group.description|safehtml}&nbsp;
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupdescription_{$group.gid}" class="z-itemcell z-w30 z-hide">
                        <textarea id="description_{$group.gid}" rows="2" cols="20" name="description_{$group.gid}">{$group.description|safehtml}</textarea>
                    </span>
                    {* *}
                    <span id="groupstate_{$group.gid}" class="z-itemcell z-w10">
                        {gt text="$group.statelbl|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupstate_{$group.gid}" class="z-itemcell z-w10 z-hide">
                        <select id="state_{$group.gid}" name="state_{$group.gid}">
                            {html_options options=$states selected=$group.state}
                        </select>
                    </span>
                    {* *}
                    <span id="groupnbuser_{$group.gid}" class="z-itemcell z-w10 z-center">
                        {$group.nbuser|safetext}
                    </span>
                    <span id="groupnbumax_{$group.gid}" class="z-itemcell z-w10 z-center">
                        {$group.nbumax|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupnbumax_{$group.gid}" class="z-itemcell z-w10 z-hide z-center">
                        <input type="text" id="nbumax_{$group.gid}" size="5" name="nbumax_{$group.gid}" value="{$group.nbumax|safetext}" />
                    </span>
                    {* *}
                    {assign var="options" value=$group.options}
                    <span id="groupaction_{$group.gid}" class="z-itemcell z-w10">
                        <button class="z-imagebutton z-hide" id="modifyajax_{$group.gid}"   title="{gt text="Edit"}">{img src=xedit.gif modname=core set=icons/extrasmall __title="Edit" __alt="Edit"}</button>
                        <a id="modify_{$group.gid}"  href="{$group.editurl|safetext}" title="{gt text="Edit"}">{img src=xedit.gif modname=core set=icons/extrasmall __title="Edit" __alt="Edit"}</a>
                        <a id="delete_{$group.gid}"     href="{$group.deleteurl|safetext}" title="{gt text="Delete"}">{img src=14_layer_deletelayer.gif modname=core set=icons/extrasmall __title="Delete" __alt="Delete"}</a>
                        <a id="members_{$group.gid}"  href="{$group.membersurl|safetext}" title="{gt text="Group membership"}">{img src=edit_group.gif modname=core set=icons/extrasmall __title="Group membership" __alt="Group membership"}</a>
                        <script type="text/javascript">
                            Element.addClassName('insert_{{$group.gid}}', 'z-hide');
                            Element.addClassName('modify_{{$group.gid}}', 'z-hide');
                            Element.addClassName('delete_{{$group.gid}}', 'z-hide');
                            Element.removeClassName('modifyajax_{{$group.gid}}', 'z-hide');
                            Event.observe('modifyajax_{{$group.gid}}', 'click', function(){groupmodifyinit({{$group.gid}})}, false);
                        </script>
                    </span>
                    <span id="editgroupaction_{$group.gid}" class="z-itemcell z-w10 z-hide">
                        <button class="z-imagebutton" id="groupeditsave_{$group.gid}"   title="{gt text="Save"}">{img src=button_ok.gif modname=core set=icons/extrasmall __alt="Save" __title="Save"}</button>
                        <button class="z-imagebutton" id="groupeditdelete_{$group.gid}" title="{gt text="Delete"}">{img src=14_layer_deletelayer.gif modname=core set=icons/extrasmall __alt="Delete" __title="Delete"}</button>
                        <button class="z-imagebutton" id="groupeditcancel_{$group.gid}" title="{gt text="Cancel"}">{img src=button_cancel.gif modname=core set=icons/extrasmall __alt="Cancel" __title="Cancel"}</button>
                    </span>
                </div>
                <div id="groupinfo_{$group.gid}" class="z-hide z-groupinfo">
                    &nbsp;
                </div>
            </li>
            {foreachelse}
            <li id="group_1" class="z-hide z-clearfix">
                <div id="groupcontent_1" class="groupcontent">
                    <input type="hidden" id="gtypeid_1" value="" />
                    <input type="hidden" id="stateid_1" value="" />
                    <input type="hidden" id="groupgid_1" value="{$group.gid}" />
                    <input type="hidden" id="modifystatus_{$group.gid}" value="0" />
                    <span id="groupgid_1" class="z-itemcell z-w05">
                        {$group.gid|safetext}
                    </span>
                    <span id="groupname_1" class="z-itemcell z-w15 z-hide">
                        {$group.name|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupname_1" class="z-itemcell z-w15">
                        <input type="text" id="name_1" name="name_1" value="" size="15" />&nbsp;
                    </span>
                    {* *}
                    <span id="groupgtype_1" class="z-itemcell z-w10 z-hide">
                        {gt text="$group.gtypelbl|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupgtype_1" class="z-itemcell z-w15">
                        <select id="gtype_1" name="gtype_1">
                            {*html_options options=$grouptype selected=$gtype*}
                            {html_options options=$grouptypes}
                        </select>
                    </span>
                    {* *}
                    <span id="groupdescription_1" class="z-itemcell z-w30 z-hide">
                        {$group.description}&nbsp;
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupdescription_1" class="z-itemcell z-w30">
                        <textarea id="description_1" rows="2" cols="20" name="description_1">{$group.description|safetext}</textarea>&nbsp;
                    </span>
                    {* *}
                    <span id="groupstate_1" class="z-itemcell z-w10 z-hide">
                        {gt text="$group.statelbl|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupstate_1" class="z-itemcell z-w10">
                        <select id="state_1" name="state_1">
                            {html_options options=$states selected=$group.state}
                        </select>
                    </span>
                    {* *}
                    <span id="groupnbuser_1" class="z-itemcell z-w10 z-hide z-center">
                        {*$group.nbuser|safetext*}&nbsp;
                    </span>
                    {* *}
                    <span id="groupnbumax_1" class="z-itemcell z-w10 z-hide z-center">
                        {$group.nbumax|safetext}
                    </span>
                    {* Hidden until called *}
                    <span id="editgroupnbumax_1" class="z-itemcell z-w10 z-center">
                        <input type="text" id="nbumax_1" size="5" name="nbumax_1" value="{$group.nbumax|safetext}" />
                    </span>
                    {* *}
                    <span id="groupaction_1" class="z-itemcell z-w12 z-hide">
                        <button class="z-imagebutton" id="modifyajax_1"   title="{gt text="Edit"}">{img src=xedit.gif modname=core set=icons/extrasmall __title="Edit" __alt="Edit"}</button>
                    </span>
                    <span id="editgroupaction_1" class="z-itemcell z-w12">
                        <button class="z-imagebutton" id="groupeditsave_1"   title="{gt text="Save"}">{img src=button_ok.gif modname=core set=icons/extrasmall __alt="Save" __title="Save"}</button>
                        <button class="z-imagebutton" id="groupeditdelete_1" title="{gt text="Delete"}">{img src=14_layer_deletelayer.gif modname=core set=icons/extrasmall __alt="Delete" __title="Delete"}</button>
                        <button class="z-imagebutton" id="groupeditcancel_1" title="{gt text="Cancel"}">{img src=button_cancel.gif modname=core set=icons/extrasmall __alt="Cancel" __title="Cancel"}</button>
                    </span>
                </div>
                <div id="groupinfo_1" class="z-hide z-groupinfo">&nbsp;</div>
            </li>
            {/foreach}
        </ol>
    </div>
    <em>{gt text="* Default user group. Cannot be deleted."}</em>

    {if $useritems}
    <h2> {gt text="Pending applications"} </h2>
    <table class="z-admintable">
        <thead>
            <tr>
                <th> {gt text="User ID"} </th>
                <th> {gt text="User name"} </th>
                <th> {gt text="Name"} </th>
                <th> {gt text="Comment"} </th>
                <th> {gt text="Actions"} </th>
            </tr>
        </thead>
        <tbody>
            {foreach item=useritem from=$useritems}
            <tr class="{cycle values='z-odd,z-even' name='pending'}">
                <td>{$useritem.userid}</td>
                <td><strong>{$useritem.username|userprofilelink}</strong></td>
                <td>{$useritem.gname}</td>
                <td>{$useritem.application|safehtml}</td>
                <td>
                    <a href="{modurl modname='Groups' type='admin' func='userpending' gid=$useritem.appgid userid=$useritem.userid action='accept'}" title="{gt text="Accept"} {$useritem.username}">{img src=add_user.gif modname=core set=icons/extrasmall __alt="Accept"}</a>&nbsp;
                    <a href="{modurl modname='Groups' type='admin' func='userpending' gid=$useritem.appgid userid=$useritem.userid action='deny'}" title="{gt text="Deny"} {$useritem.username}">{img src=delete_user.gif modname=core set=icons/extrasmall __alt="Deny"}</a>
                </td>
            </tr>
            {foreachelse}
            <tr class="z-admintableempty"><td colspan="5">{gt text="No items found."}</td></tr>
            {/foreach}
        </tbody>
    </table>
    {/if}

    {pager rowcount=$pager.numitems limit=$pager.itemsperpage posvar='startnum'}
</div>

<script type="text/javascript">
    Event.observe(window, 'load', function(){groupinit({{$defaultgroup}},{{$groups[0].gid}},{{$primaryadmingroup}});}, false);

    // some defines
    var updatinggroup = '...{{gt text="Updating group"}}...';
    var deletinggroup = '...{{gt text="Deleting group"}}...';
    var confirmDeleteGroup = '{{gt text="Do you really want to delete this group?"}}';
</script>
