// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMD module for the mod_quickpoll voting widget.
 *
 * @module     mod_quickpoll/poll_renderer
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Applies the rendered percentage values to Bootstrap progress bars.
     *
     * @param {HTMLElement} root Widget root.
     */
    const initialiseBars = root => {
        root.querySelectorAll('[data-region="quickpoll-bar"]').forEach(bar => {
            const percent = parseFloat(bar.dataset.percent || '0');
            bar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        });
    };

    /**
     * Finds an option object in a result payload.
     *
     * @param {Object} results Result payload returned by the web service.
     * @param {number} optionid Option id.
     * @returns {Object|null}
     */
    const findOptionResult = (results, optionid) => {
        if (!results || !results.questions) {
            return null;
        }

        for (const question of results.questions) {
            for (const option of question.options) {
                if (parseInt(option.id, 10) === optionid) {
                    return option;
                }
            }
        }

        return null;
    };

    /**
     * Updates all visible result counters and bars.
     *
     * @param {HTMLElement} root Widget root.
     * @param {Object} results Result payload returned by the web service.
     */
    const updateResults = (root, results) => {
        if (!results || !results.canviewresults) {
            return;
        }

        root.querySelectorAll('.mod-quickpoll-option').forEach(optionrow => {
            const optionid = parseInt(optionrow.dataset.optionId, 10);
            const result = findOptionResult(results, optionid);
            if (!result) {
                return;
            }

            const count = optionrow.querySelector('[data-region="quickpoll-count"]');
            const progress = optionrow.querySelector('.progress');
            const bar = optionrow.querySelector('[data-region="quickpoll-bar"]');

            if (count) {
                count.textContent = result.count;
            }

            if (progress) {
                progress.setAttribute('aria-valuenow', result.percent);
            }

            if (bar) {
                const percent = Math.max(0, Math.min(100, parseFloat(result.percent || 0)));
                bar.dataset.percent = percent;
                bar.style.width = `${percent}%`;
            }
        });
    };

    /**
     * Displays a message inside the widget.
     *
     * @param {HTMLElement} root Widget root.
     * @param {string} message Message text.
     */
    const showMessage = (root, message) => {
        const messageRegion = root.querySelector('[data-region="quickpoll-message"]');
        if (!messageRegion || !message) {
            return;
        }

        messageRegion.textContent = message;
        messageRegion.classList.remove('d-none');
    };

    /**
     * Submits a vote via Moodle AJAX.
     *
     * @param {HTMLElement} root Widget root.
     * @param {HTMLElement} button Vote button.
     * @param {number} cmid Course module id.
     */
    const submitVote = async(root, button, cmid) => {
        const anonymous = root.querySelector('[data-region="quickpoll-anonymous"]');
        button.disabled = true;

        try {
            const response = await Ajax.call([{
                methodname: 'mod_quickpoll_submit_vote',
                args: {
                    cmid: cmid,
                    questionid: parseInt(button.dataset.questionId, 10),
                    optionid: parseInt(button.dataset.optionId, 10),
                    anonymous: anonymous ? anonymous.checked : false,
                },
            }])[0];

            showMessage(root, response.message);
            updateResults(root, response.results);

            if (root.dataset.allowMultiple !== '1') {
                const selector = `[data-question-id="${button.dataset.questionId}"] [data-action="quickpoll-vote"]`;
                root.querySelectorAll(selector).forEach(questionbutton => {
                    questionbutton.disabled = true;
                });
            }
        } catch (error) {
            button.disabled = false;
            Notification.exception(error);
        }
    };

    /**
     * Starts the Server-Sent Events connection when supported.
     *
     * @param {HTMLElement} root Widget root.
     */
    const initStream = root => {
        if (!root.dataset.streamUrl || typeof EventSource === 'undefined') {
            return;
        }

        const source = new EventSource(root.dataset.streamUrl);

        source.addEventListener('results', event => {
            updateResults(root, JSON.parse(event.data));
        });

        source.addEventListener('closed', () => {
            source.close();
            window.location.reload();
        });
    };

    /**
     * Initialises a quickpoll widget instance.
     *
     * @param {number} cmid Course module id.
     */
    const init = cmid => {
        const root = document.querySelector(`[data-region="quickpoll"][data-cmid="${cmid}"]`);
        if (!root) {
            return;
        }

        initialiseBars(root);
        initStream(root);

        root.addEventListener('click', event => {
            const button = event.target.closest('[data-action="quickpoll-vote"]');
            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();
            submitVote(root, button, cmid);
        });
    };

    return {
        init: init,
    };
});
