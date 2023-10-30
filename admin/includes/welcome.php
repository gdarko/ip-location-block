<?php
/**
 * This file comes from the "IP Location Block" WordPress plugin.
 * https://darkog.com/p/ip-location-block/
 *
 * Copyright (C) 2020-2023  Darko Gjorgjijoski. All Rights Reserved.
 *
 * IP Location Block is free software; you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * IP Location Block program is distributed in the hope that it
 * will be useful,but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License v3
 * along with this program;
 *
 * If not, see: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Code written, maintained by Darko Gjorgjijoski (https://darkog.com)
 */

// Urls
$url_docs      = 'https://iplocationblock.com/codex/?utm_source=plugin&utm_medium=welcome&utm_campaign=codex_views';
$url_purchase  = 'https://iplocationblock.com/pricing/?utm_source=plugin&utm_medium=welcome&utm_campaign=api_signups';
$url_prem_docs = 'https://iplocationblock.com/codex/city-state-level-matching/?utm_source=plugin&utm_medium=welcome&utm_campaign=city_state_matching';
$url_github    = 'https://github.com/gdarko/ip-location-block/';
$url_wordpress = 'https://wordpress.org/support/plugin/ip-location-block/';
?>

<div class="instructions ilb-instructions">
    <div class="ilb-instructions-card ilb-instructions-card-shadow">
        <div class="ilb-instructions-row ilb-instructions-header">
            <div class="ilb-instructions-colf">
                <p class="lead"><?php _e( 'Thanks for installing <strong class="green">IP Location Block</strong>', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( 'IP Location Block provides complete <strong class="underline">geolocation blocking</strong> solution to block unwanted visitors on your website.', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( 'The plugin is <strong>FREE</strong> and supports geolocation based blacklisting/whitelisting by country. City and State matching is ONLY available if you use the <a href="https://iplocationblock.com/pricing/" target="_blank">IP Location Block API</a> geolocation provider.', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( 'If you found this plugin <strong>useful</strong> useful, we will greatly appreciate if you take a minute to <a target="_blank" title="Give this plugin a good five star rating :)" href="https://wordpress.org/support/plugin/ip-location-block/reviews/#new-post">rate it. &#9733;&#9733;&#9733;&#9733;&#9733;</a>', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( sprintf( '<a target="_blank" class="button button-primary" title="Plugin Documentation" href="%s">Read Docs</a>', $url_docs ), 'ip-location-block' ); ?></p>
            </div>
        </div>
        <div class="ilb-instructions-row ilb-instructions-mb-10">
            <div class="ilb-instructions-colf">
                <div class="ilb-instructions-extra">
                    <h4 style="margin-top:0;"
                        class="navy"><?php _e( 'Need <span style="text-decoration: underline;">city</span> or <span style="text-decoration: underline;">state</span> level blocking?', 'ip-location-block' ); ?></h4>
                    <p>
						<?php _e( sprintf( 'If you need a better and more precise IP Geo-Location matching by <strong>CITY</strong> and <strong>STATE</strong>, sign up for a <a target="_blank" href="%s">premium plan</a> or read <a target="_blank" href="%s">documentation</a>.', $url_purchase, $url_prem_docs ), 'ip-location-block' ); ?>
                    </p>
                </div>
            </div>
            <div class="ilb-instructions-colf" style="padding-top:0;">
                <div class="ilb-instructions-extra">
                    <h4 class="navy"><?php _e( 'Found problem? Report it!', 'ip-location-block' ); ?></h4>
                    <p style="margin-bottom: 0;">
						<?php _e( sprintf( 'If you found a bug, or you want to report a problem please open a support ticket <a target="_blank" href="%s">here</a> or on <a target="_blank" href="%s">Github!</a>', $url_wordpress, $url_github ), 'ip-location-block' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
add_action( 'admin_footer', function () {
	?>
    <style>
        /**
	 * Instructions
	 */
        .ilb-instructions {
            width: 100%;
            max-width: 98.5%;
        }

        .instructions .ilb-instructions-card {
            background: #fff;
            display: inline-block;
            position: relative;
            width: 100%;
            max-width: 100%;
        }

        .instructions .ilb-instructions-card .ilb-instructions-header {
            border-bottom: 1px solid #e3e3e3;
            padding-bottom: 10px;
        }

        .instructions .ilb-instructions-card .ilb-instructions-header p.lead {
            font-size: 23px;
            color: #1a1a1a;
            margin-bottom: 5px;

        }

        .instructions .ilb-instructions-card .ilb-instructions-header p.desc {
            font-size: 15px;
        }

        .instructions .ilb-instructions-row {
            float: left;
            width: 96%;
        }

        .instructions .ilb-instructions-colf {
            width: 100%;
            float: left;
            padding: 1%;
        }

        .instructions .ilb-instructions-col3 {
            width: 31%;
            float: left;
            padding: 1%;
        }

        .instructions .ilb-instructions-col4 {
            width: 23%;
            float: left;
            padding: 1%;
        }

        .instructions h4 {
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .ilb-notice-dismiss {
            text-decoration: none;
        }

        @media (max-width: 767px) {
            .instructions .ilb-instructions-col3 {
                width: 100%;
            }

            .instructions .ilb-instructions-col4 {
                width: 100%;
            }
        }

        .instructions .green {
            color: #1abc9c;
        }

        .instructions .navy {
            color: #2f4154;
            font-size: 17px;
        }

        .instructions .ilb-instructions-row.ilb-instructions-header .ilb-instructions-colf {
            padding-bottom: 0;
        }

        .instructions .ilb-instructions-link {
            background-color: #2f4154;
            padding: 10px;
            min-width: 80px;
            margin-top: 10px;
            margin-bottom: 10px;
            color: #1abc9c;
            font-size: 1rem;
        }

        .instructions .ilb-instructions-link:hover, .instructions .ilb-instructions-link:active, .instructions .ilb-instructions-link:focus {
            outline: none;
            opacity: 0.8;
        }

        .instructions a.ilb-instructions-link {
            text-transform: none;
            text-decoration: none;
        }

        .instructions.is-dismissible {
            padding-right: 0 !important;
        }

    </style>
	<?php
}, PHP_INT_MAX );
?>
