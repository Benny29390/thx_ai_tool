<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// get all messages by uid 
global $wpdb;
$messages = $wpdb->get_results("SELECT * FROM wpt_tallyr_messages WHERE uid = " . get_current_user_id() . " ORDER BY date DESC");


?>
<div>
    <div id="allcomments">
        <div class="headingta">
            <div class="container">
                <h2>Kommentare</h2>
            </div>
        </div>
        <div class="container">
            <?php
            if ($messages) {

                // take all messages with same tid and show them together
                $lasttid = 0;
                $groupedmessages = array();


                foreach ($messages as $message) {
                    if ($message->tid != $lasttid) {
                        $lasttid = $message->tid;
                        $groupedmessages[$lasttid] = array();
                    }
                    $groupedmessages[$lasttid][] = $message;
                }

                foreach ($groupedmessages as $messages) {
                    $class = '';
                    if (!$messages[0]->seenby) {
                        $class = ' newmessage';
                    }

                    echo '<div class="commentwrap'.$class.'">';
                    echo '<div class="comment"><div class="date">'.date('d.m.Y H:i', strtotime($messages[0]->date)).'</div></div>';
                    // get time 
                    $time = $wpdb->get_row("SELECT * FROM wpt_tallyr_times WHERE id = " . $messages[0]->tid);
                    if ($time) {
                        // get client
                        $client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE id = " . $time->clientid);
                        $parentclient = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE id = " . $client->parentclient);
                        if ($client) {
                            echo '<div class="info"><strong>Kunde: </strong>' . esc_html($client->title) . '</div>';
                            if ($parentclient) {
                                echo '<div class="info"><strong>Haupt-Kunde: </strong>' . esc_html($parentclient->title) . '</div>';
                            }
                            echo '<div class="info"><strong>Aufgabe: </strong>' . esc_html($time->description) . '</div>';
                            echo '<div class="info"><strong>Datum: </strong>' . date('d.m.Y', strtotime($time->workdate)) . '</div>';
                            echo '<div class="info"><strong>Stunden: </strong>' . esc_html($time->hours) . '</div>';

                        }
                        //echo '<div class="message"><strong>Kommentar: </strong>'.tallyercheckIfLink(nl2br($messages[0]->message)).'</div>';
                        // shwo all messages with this tid
                        echo '<div class="messages">';
                        // turn around array to show oldest first
                        $messagesout = array_reverse($messages);
                        foreach ($messagesout as $msg) {
                            if (!$msg->message) {
                                continue;
                            }
                            echo '<div class="message fr_'.$msg->fr.'"><strong>' . date('d.m.Y H:i', strtotime($msg->date)) . ':</strong> ' . tallyercheckIfLink(nl2br($msg->message)) . '</div>';
                        }
                        echo '</div>';

                        echo '<form class="sendreply" data-tid="'.$messages[0]->tid.'" data-mid="'.$messages[0]->mid.'">
                            <textarea required name="reply" placeholder="Antworten..."></textarea>
                            <button class="sendreply" type="submit" data-tid="'.$messages[0]->tid.'" data-mid="'.$messages[0]->mid.'">Antwort senden</button>
                            </form>';
                    }

                    if (!$messages[0]->seenby) {
                        //echo '<button class="readandaccept" data-mid="'.$messages[0]->mid.'" data-timeid="'.$messages[0]->tid.'">Markieren als gesehen und erledigt</button>';
                    } else {
                        //echo '<button class="settoundone" data-mid="'.$messages[0]->mid.'" data-timeid="'.$messages[0]->tid.'">Unerledigt setzen</button>';
                    }

                    echo '</div>';
                }
            } else {
                echo '<p>Noch keine Nachrichten vorhanden.</p>';
            }
            ?>
        </div>
    </div>

</div>