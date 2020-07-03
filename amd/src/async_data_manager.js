// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AMD module to manage temporary table setup and cleanup
 *
 * @package    tool_textcleanup
 * @copyright  2020 - CALL Learning - Laurent David <laurent@call-learning>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/str', 'core/notification'],
    function ($, Ajax, Str, Notification) {

        var MAX_RECORDS = 10;

        function asyncCall(methodname, args) {
            var request = {
                methodname: methodname,
                args: args
            };
            var promise = Ajax.call([request])[0];

            return promise;
        }

        function cleanupText(search, types, rowcount, donerecords, cleanupbutton, infoarea) {
            asyncCall('tool_textcleanup_cleanup_text', {
                'query': search,
                types: types,
                maxrecords: MAX_RECORDS
            }).done(function (data) {
                infoarea.text(donerecords + '/' + rowcount);
                if (data) {
                    if (data.cleanedrecords === 0) {
                        cleanupbutton.prop("disabled", false);
                        infoarea.text('');
                    } else {
                        cleanupText(search, types, rowcount, donerecords, cleanupbutton, infoarea);
                    }
                }
            }).fail(function (ex) {
                cleanupbutton.prop("disabled", false);
                Notification.exception(ex);
            })
        }

        return {
            init: function (loadformid, cleanupformid, isloading, search, types, rowcount) {
                $('document').ready(function () {
                    Str.get_strings([
                        {key: 'dataloading', component: 'tool_textcleanup'},
                        {key: 'reloaddata', component: 'tool_textcleanup'},
                    ]).then(function (strings) {

                        // Load / Reload data.
                        var loadbutton = $('#' + loadformid + " input[type=submit]").first();
                        if (isloading) {
                            loadbutton.val(strings[0]);
                        } else {
                            loadbutton.val(strings[1]);
                        }
                        loadbutton.click(function (e) {
                            e.preventDefault();
                            loadbutton.val(strings[0]);
                            loadbutton.prop("disabled", true);

                            asyncCall('tool_textcleanup_build_text_table', []).done(function () {
                                loadbutton.val(strings[1]);
                                loadbutton.prop("disabled", false);
                            }).fail(function (ex) {
                                loadbutton.prop("disabled", false);
                                loadbutton.val(strings[0]);
                                Notification.exception(ex);
                            })
                        });

                        // Cleanup text.
                        var cleanupbutton = $('#' + cleanupformid + " input[type=submit]").first();
                        cleanupbutton.click(function (e) {
                            e.preventDefault();
                            cleanupbutton.prop("disabled", true);
                            var infoarea = cleanupbutton.append('div');
                            cleanupText(search, types, rowcount, 0, cleanupbutton, infoarea);
                        });

                    }).fail(Notification.exception);
                });
            }
        };
    });
