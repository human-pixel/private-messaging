<?php

/**
 * Author: WPMU DEV
 * Name: Group Conversation (Beta)
 * Description: Enable include more people to a conversation
 */
class MM_Group_Conversation
{
    public function __construct()
    {
        add_action('mm_before_reply_form', array(&$this, 'include_textbox'));
        add_action('wp_ajax_mm_suggest_include_users', array(&$this, 'mm_suggest_include_users'));
        add_action('mm_before_subject_field', array(&$this, 'append_cc_textbox'), 10, 3);
        add_action('wp_footer', array(&$this, 'cc_suggest_script'));
        add_action('message_content_meta', array(&$this, 'show_user_list'));
    }

    function show_user_list($message)
    {
        $conversation = MM_Conversation_Model::model()->find($message->conversation_id);
        if (!is_object($conversation)) {
            return;
        }

        //todo remove user from conversation for owner
        $users = get_users(array(
            'include' => $conversation->users_index
        ));

        foreach ($users as $user) {
            $name = $user->first_name . ' ' . $user->last_name;
            if (strlen(trim($name)) == 0) {
                $name = $user->user_login;
            }
            echo '<span class="label label-default">' . $name . '</span> ';
        }
    }

    function append_cc_textbox($model, $form, $scenario)
    {
        switch ($scenario) {
            case 'compose_form':
                $this->_compose_form_cc();
                break;
        }
    }

    function _compose_form_cc()
    {
        ?>
        <div class="clearfix"></div>
        <div class="form-group">
            <label class="control-label col-sm-2"><?php _e("Cc", mmg()->domain) ?></label>

            <div class="col-md-10 col-sm-12 col-xs-12">
                <input type="text" name="cc" id="mmg-cc-input" class="form-control cc-input"
                       placeholder="<?php esc_attr_e("Cc users", mmg()->domain) ?>">
            </div>
            <div class="clearfix"></div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                window.mm_cc_input = $('#mmg-cc-input').selectize({
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                    options: [],
                    create: false,
                    load: function (query, callback) {
                        if (!query.length) return callback();
                        var instance = window.mm_cc_input[0].selectize;
                        $.ajax({
                            type: 'POST',
                            url: '<?php echo admin_url('admin-ajax.php?action=mm_suggest_users&_wpnonce='.wp_create_nonce('mm_suggest_users')) ?>',
                            data: {
                                'query': query
                            },
                            beforeSend: function () {
                                instance.$control.append('<i style="position: absolute;right: 10px;" class="fa fa-circle-o-notch fa-spin"></i>');
                            },
                            success: function (data) {
                                instance.$control.find('i').remove();
                                callback(data);
                            }
                        });
                    }
                });
            })
        </script>
    <?php
    }

    function mm_suggest_include_users()
    {
        if (!wp_verify_nonce(mmg()->get('_wpnonce'), 'mm_suggest_include_users')) {
            return;
        }
        $model = MM_Conversation_Model::model()->find(mmg()->post('parent_id'));
        if (!is_object($model)) {
            return;
        }
        $excludes = explode(',', $model->user_index);
        $query_string = mmg()->post('query');
        if (!empty($query_string)) {
            $query = new WP_User_Query(array(
                'search' => '*' . mmg()->post('query') . '*',
                'search_columns' => array('user_login'),
                'exclude' => $excludes,
                'number' => 10,
                'orderby' => 'user_login',
                'order' => 'ASC'
            ));
            $name_query = new WP_User_Query(array(
                'exclude' => $excludes,
                'number' => 10,
                'orderby' => 'user_login',
                'order' => 'ASC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'first_name',
                        'value' => $query_string,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'last_name',
                        'value' => $query_string,
                        'compare' => 'LIKE'
                    )
                )
            ));
            $results = array_merge($query->get_results(), $name_query->get_results());

            $data = array();
            foreach ($results as $user) {
                $userdata = get_userdata($user->ID);
                $name = $user->user_login;
                $full_name = trim($userdata->first_name . ' ' . $userdata->last_name);
                if (strlen($full_name)) {
                    $name = $user->user_login . ' - ' . $full_name;
                }
                $obj = new stdClass();
                $obj->id = $user->ID;
                $obj->name = $name;
                $data[] = $obj;
            }
            wp_send_json($data);
        }

        die;
    }

    function include_textbox($model)
    {
        ?>
        <div class="form-group">
            <label class="col-md-12 hidden-xs hidden-sm">
                <?php _e("Include more:", mmg()->domain) ?>
            </label>

            <div class="col-md-12 col-xs-12 col-sm-12">
                <input type="text" name="user_include" id="user_include" class="form-control">
            </div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                window.mm_reply_select = $('#user_include').selectize({
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                    options: [],
                    create: false,
                    load: function (query, callback) {
                        if (!query.length) return callback();

                        $.ajax({
                            type: 'POST',
                            url: '<?php echo admin_url('admin-ajax.php?action=mm_suggest_include_users&_wpnonce='.wp_create_nonce('mm_suggest_include_users')) ?>',
                            data: {
                                'query': query,
                                'parent_id': '<?php echo $model->conversation_id ?>'
                            },
                            beforeSend: function () {
                                $('.selectize-input').append('<i style="position: absolute;right: 10px;" class="fa fa-circle-o-notch fa-spin"></i>');
                            },
                            success: function (data) {
                                $('.selectize-input').find('i').remove();
                                callback(data);
                            }
                        });
                    }
                });
            })
        </script>
    <?php
    }

    function cc_suggest_script()
    {
        ?>

    <?php
    }
}

new MM_Group_Conversation();