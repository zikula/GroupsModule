{adminheader}
<h3>
    <span class="fa fa-trash-o"></span>
    {gt text='Remove user from group'}
</h3>

<p class="alert alert-warning">
    {gt text='Do you really want to remove user "%1$s" from group "%2$s"?' tag1=$uname tag2=$group.name}
</p>

<form class="form-horizontal" role="form" action="{route name='zikulagroupsmodule_admin_removeuser' gid=$gid|safetext uid=$uid|safetext}" method="post" enctype="application/x-www-form-urlencoded">
    <fieldset>
        <input type="hidden" name="csrftoken" value="{insert name='csrftoken'}" />
        <input type="hidden" name="confirmation" value="1" />
        <legend>{gt text='Confirmation prompt'}</legend>
        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button class='btn btn-danger' title="{gt text='Remove'}">{gt text='Remove'}</button>
                <a class="btn btn-default" href="{route name='zikulagroupsmodule_admin_groupmembership' gid=$gid}" title="{gt text='Cancel'}">{gt text='Cancel'}</a>
            </div>
        </div>
    </fieldset>
</form>
{adminfooter}
