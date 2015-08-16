{adminheader}
<h3>
    <span class="fa fa-plus"></span>
    {gt text='Create new group'}
</h3>

<form id="gg" class="form-horizontal" role="form" action="{route name='zikulagroupsmodule_admin_create'}" method="post" enctype="application/x-www-form-urlencoded">
    <fieldset>
        <input type="hidden" id="csrftoken" name="csrftoken" value="{insert name='csrftoken'}" />
        <div class="form-group">
            <label class="col-sm-3 control-label required" for="group_name">{gt text='Name'}</label>
            <div class="col-sm-9">
                <input id="group_name" name="name" type="text" class="form-control" size="30" maxlength="255" required="required" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label" for="groups_gtype">{gt text='Type'}</label>
            <div class="col-sm-9">
                <select class="form-control" id="groups_gtype" name="gtype">
                    {html_options options=$grouptype default='0'}
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label" for="groups_state">{gt text='State'}</label>
            <div class="col-sm-9">
                <select class="form-control" id="groups_state" name="state">
                    {html_options options=$groupstate default='0'}
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label" for="groups_nbumax">{gt text='Maximum membership'}</label>
            <div class="col-sm-9">
                <input id="groups_nbumax" name="nbumax" type="number" class="form-control" size="10" maxlength="10" min="0" value="0" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label" for="groups_description">{gt text='Description'}</label>
            <div class="col-sm-9">
                <textarea class="form-control" id="groups_description" name="description" rows="5"></textarea>
            </div>
        </div>
    </fieldset>
    <span class="required"></span>{gt text='Required values'}

    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
            <button class="btn btn-success" title="{gt text='Save'}">{gt text='Save'}</button>
            <a class="btn btn-danger" href="{route name='zikulagroupsmodule_admin_view'}" title="{gt text='Cancel'}">{gt text='Cancel'}</a>
        </div>
    </div>
</form>
{adminfooter}
