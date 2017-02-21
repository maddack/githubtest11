<?php
require_once dirname( __FILE__ ) . '/helpers.php';

class Event{

	public static function getUsersAddonPath () {
		if (!class_exists("eventBookingProUsersClass")) {
			$addonPath = 'wp-content/plugins/eventBookingProUsers/eventBookingProUsersClass.php';
			if (is_file($addonPath)) {
				include_once($addonPath);
				return true;
			}
			return false;
		} else {
			return true;
		}
	}

	public static function eventBelongsToCategories($id, $categories) {
		if ($categories == NULL || $categories == "") return true;

		global $wpdb;
		$okay = false;

		$categories = preg_replace('/\s+/', '', $categories);
		$catIDs = explode(",", $categories);

		$results = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("categoryEvents")." where event='$id'");
		foreach ($results as $result ) {
			if (in_array($result->category, $catIDs)) {
				$okay = true;
				break;
			}
		}

		return $okay;
	}


 public static function eventBelongsToMonths($startDate, $months) {
    if ($months == NULL || $months == "") return true;

    $months = preg_replace('/\s+/', '', $months);
    $months = explode(",", $months);

    $eventMonth = intval(substr($startDate, strpos($startDate, '-')+1, strrpos($startDate, '-')-strpos($startDate, '-')-1));

    return in_array($eventMonth, $months);
  }

  public static function eventBelongsToNextDays($startDate, $nextdays) {
    if ($nextdays == NULL || $nextdays == "") return true;

    $startDate = $startDate.' 00:00:00';

    $nextdays = intval($nextdays);

    $eventDate = date_create($startDate);
    $today = new DateTime();
    $today->setTime(0, 0);
    $interval = date_diff($today, $eventDate);
    $diff = intval($interval->format('%R%a'));

    return ($diff  >=  0 && $diff <= $nextdays);
  }



	public static function occurenceHasPassed ($occur) {
		if (!$occur)  return false;

		$today = intval(current_time('Ymd'));
		$currentTime = strtotime(current_time("H:i:s"));

		$eventStartDateObj = new DateTime($occur->start_date);
		$eventStartDate = intval($eventStartDateObj->format('Ymd'));
		$eventStartTime = strtotime($occur->start_time);

		// previous days
		if ($today > $eventStartDate) return true;

		// same day but passed time
		if ($today == $eventStartDate && $currentTime > $eventStartTime) return true;

    return false;
	}

	public static function bookingOpen ($occur) {
		if (!$occur)  return 3;

		$startsDirectly = $occur->bookingDirectly == 'true';
		$endsWithEvent = $occur->bookingEndsWithEvent == 'true';

		// no restrictions
		if ($startsDirectly && $endsWithEvent) return 0;

		$today = intval(current_time('Ymd'));
		$currentTime = strtotime(current_time("H:i:s"));;

		$bookingStartDateObj = new DateTime($occur->startBooking_date);
		$bookingStartDate = intval($bookingStartDateObj->format('Ymd'));
		$bookingStartTime = strtotime($occur->startBooking_time);


		$bookingEndDateObj = new DateTime($occur->endBooking_date);
		$bookingEndDate = intval($bookingEndDateObj->format('Ymd'));
		$bookingEndTime = strtotime($occur->endBooking_time);

		if (!$startsDirectly && ($today < $bookingStartDate || $today == $bookingStartDate && $currentTime < $bookingStartTime)) {
			return 1;
		}

		if (!$endsWithEvent && ($today > $bookingEndDate || $today == $bookingEndDate && $currentTime > $bookingEndTime)) {
			return 2;
		}

    return 0;
	}

	public static function bookingsPerTicket($dateId, $ticketId) {
		global $wpdb;

		$ticketRow = $wpdb->get_row( "SELECT *  FROM " . EventBookingHelpers::getTableName("tickets")." where id='$ticketId' ");

		//For the ticket
		$bookings = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("payments")." where date_id='$dateId' and ticket_id='$ticketId'");

		$ticketBookedCount = 0;

		foreach($bookings as $bookingA) {
			if ($spotsLeftStrict == "false")
				$ticketBookedCount += intval($bookingA->quantity);
			else{
				$bookingStatus= $bookingA->status;
				if ( strcasecmp($bookingStatus,"paid") == 0 ||  strcasecmp($bookingStatus,"not paid") == 0 ||
				strcasecmp($bookingStatus,"completed") == 0 ||
				strcasecmp($bookingStatus,"success") == 0 || strcasecmp($bookingStatus,"successful") == 0
				||strcasecmp($bookingStatus,"successfull") == 0 )
					$ticketBookedCount += intval($bookingA->quantity);
			}
		}

		$left = intval($ticketRow->allowed) - $ticketBookedCount;

		return $left;
	}

	public static function checkSpots($dateId, $ticketId) {
		global $wpdb;

		$spotsLeftStrict = $wpdb->get_var( "SELECT spotsLeftStrict FROM " . EventBookingHelpers::getTableName("settings")." where id=1 ");

		$ticketRow = $wpdb->get_row( "SELECT *  FROM " . EventBookingHelpers::getTableName("tickets")." where id='$ticketId' ");

		$eventID = $ticketRow->event;

		$event = $wpdb->get_row("SELECT *  FROM " . EventBookingHelpers::getTableName("events")." where id='$eventID' ");

		$maxSpots = intval($event->maxSpots);

		$bookingsAll = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("payments")." where date_id='$dateId' and event_id='$eventID'");

		$allBookingsForThisDate = 0;

		foreach($bookingsAll as $bookingA) {
			if ($spotsLeftStrict == "false")
				$allBookingsForThisDate += intval($bookingA->quantity);
			else{
				$bookingStatus = $bookingA->status;
				if ( strcasecmp($bookingStatus,"paid") == 0 ||  strcasecmp($bookingStatus,"not paid") == 0 ||
				strcasecmp($bookingStatus,"completed") == 0 ||
				strcasecmp($bookingStatus,"success") == 0 || strcasecmp($bookingStatus,"successful") == 0
				||strcasecmp($bookingStatus,"successfull") == 0 )
					$allBookingsForThisDate += intval($bookingA->quantity);
			}
		}

		$leftAll = $maxSpots - $allBookingsForThisDate;


		//For the ticket
		$bookings = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("payments")." where date_id='$dateId' and ticket_id='$ticketId'");

		$ticketBookedCount = 0;

		foreach($bookings as $bookingA) {
			if ($spotsLeftStrict == "false")
				$ticketBookedCount += intval($bookingA->quantity);
			else{
				$bookingStatus= $bookingA->status;
				if ( strcasecmp($bookingStatus,"paid") == 0 ||  strcasecmp($bookingStatus,"not paid") == 0 ||
				strcasecmp($bookingStatus,"completed") == 0 ||
				strcasecmp($bookingStatus,"success") == 0 || strcasecmp($bookingStatus,"successful") == 0
				||strcasecmp($bookingStatus,"successfull") == 0 )
					$ticketBookedCount += intval($bookingA->quantity);
			}
		}

		$left = intval($ticketRow->allowed) - $ticketBookedCount;

		if($maxSpots > 0) {
			$left = ($left >= $leftAll) ? $leftAll : $left;
		}

		return $left;
	}


	public static function getEventDateMarkUp($data, $dateId, $settingsOption, $upcomingDates, $passedDates, $forceFirst=false, $disableMoreBtn=false) {
			//date
			$id = $data->id;
			$today = current_time('Y-m-d');
			$date_id=  $dateId;
			$p = 0;
			$passed = '';
			$passedTemp = '';
			$upcoming = '';
			$upcomingTemp = '';

			if ($passedDates != NULL) {
				$passedTemp = '<div class="side"><div class="title" style="'.$settingsOption["modalTitleStyle"].'">Passed Dates:</div>';
				foreach($passedDates as $dateRow) {

					if (!self::occurenceHasPassed($dateRow)) continue;

					$p++;
					if ($p == 1 & $dateId < 0) {
						$mainDateObj = $dateRow;
						$date_id = $dateRow->id;
					} else if (intval($dateId) ==  intval($dateRow->id)) {
						$mainDateObj = $dateRow;
						$date_id = $dateRow->id;
					}

					if ($p > 1)
						$passed .= '<div style="display:block; width:100%; height:'.$settingsOption["moreDateSectionMarginBottom"].'px;"></div>';

					$passed .= self::getDateMarkUp($settingsOption, false, false, $dateRow, true, true, -2);

				}

				if ($p > 0) {
					$passed = $passedTemp.$passed	.'</div>';
				}
			}

			$i = 0;

			if ($upcomingDates != NULL) {
				$upcomingTemp.='<div class="side"><div class="title" style="'.$settingsOption["modalTitleStyle"].'">Upcoming Dates:</div>';
				foreach($upcomingDates as $dateRow) {

					if (self::occurenceHasPassed($dateRow)) continue;

					$i++;
					if ($i == 1 & $dateId < 0) {
						$mainDateObj = $dateRow;
						$date_id = $dateRow->id;
					} else if (intval($dateId) ==  intval($dateRow->id)) {
						$mainDateObj = $dateRow;
						$date_id = $dateRow->id;
					}

					if ($i > 1) $upcoming.='<div style="display:block; width:100%; height:'.$settingsOption["moreDateSectionMarginBottom"].'px;"></div>';

					$upcoming .= self::getDateMarkUp($settingsOption, false, false, $dateRow, true, true, $id, $date_id, $dateRow->id);
				}
				if ($i > 0)
					$upcoming = $upcomingTemp.$upcoming.'</div>';
			}

			if ($settingsOption["moreDateUpcoming"] == "false") $upcoming = '';

			$moreDatesTxts = $upcoming.$passed;

			if (intval($dateId) > -1) $date_id = $dateId;

			$html = '<div class="dateDetails" style="'.$settingsOption["date"].'">';

			$html .= self::getDateMarkUp($settingsOption, $forceFirst, false, $mainDateObj);

			if (!$disableMoreBtn) {
				if (($i+$p > 1 & $settingsOption["moreDatesOn"] == "true") || ($settingsOption["moreDatesOn"] == "true" && $settingsOption["settings"]->permenantMoreButton == "true")) {
					$html .= '<style>
								.moreDates{margin-top:'.$settingsOption["moreDateMarginTop"].'px;}
								.moreDates a{color: '.$settingsOption["moreDateColor"].';float: '.$settingsOption["moreDateTextAlign"].'; font-size:'.$settingsOption["moreDateSize"].'px; line-height:120%; '.$settingsOption["moreDatefontStyle"].'}
								.moreDates a:hover{color: '.$settingsOption["moreDateHoverColor"].';  }
						</style>';
					$html .= '<div class="moreDates">';
					$html .= '<a class="md-trigger isMoreDate" data-modal="moreDetails'.$id.$date_id.'">'.$settingsOption["moreDateTxt"].'</a>';
					$html .= '</div>';

					$html .= '<div class="md-modal md-fullpage"  id="moreDetails'.$id.$date_id.'">';
						$html .= '<div class="md-content">';
						$html .= '<div class="closeBtn"><a href="#" class=" boxBtns">x</a></div>';
								$html .= '<div class="title" style="'.$settingsOption["modalTitle"].'">'.stripslashes($data->name).'</div>';
								$html .= $moreDatesTxts;


					$html .= '</div>';
					$html .= '</div>';
				}
			}
			$html .= '</div>';

		$date = new DateTime($mainDateObj->start_date);
		return array('dateID'=>$date_id, 'html'=>$html, 'date'=>$date, 'start_time'=>$mainDateObj->start_time, 'occurrence'=>$mainDateObj);
	}

	public static function getDateMarkUp($settingsOption, $forceOnlyFirst, $forceBoth, $dateObj, $modal=false, $bookBtn=false, $id = -1, $dateId = -1, $toOpenDate = -1) {
		$suffix = $modal?"modal_":"";

		$start_date = $dateObj->start_date;
		$end_date = $dateObj->end_date;

		$start_time = $dateObj->start_time;
		$end_time = $dateObj->end_time;

		$date_format = EventBookingHelpers::convertDateFormat($settingsOption["dateFormat"]);
		$time_format = $settingsOption["timeFormat"];

		$dateLanguaged_start = utf8_encode(strftime($date_format, strtotime($start_date)));
		$dateLanguaged_end = utf8_encode(strftime($date_format, strtotime($end_date)));

		$startTime = date($time_format, strtotime($start_time));
		$endTime = date($time_format, strtotime($end_time));

		$html = '<div class="dateCnt">';
			$html .= '<div class="dates">';
				$html .= '<div class="dateWrap">';
					if (!$forceOnlyFirst && ($settingsOption["dateEnds"] == "true" || $forceBoth))
						$html .= '<div class="datelabel" style="'.$settingsOption[$suffix."dateLabel"].'">'.$settingsOption["statsOnTxt"].'</div>';
					$html .= '<div class="date">'.$dateLanguaged_start.'</div>';

					$html .= '<div class="time">'.$startTime.'</div>';

				$html .= '</div>';

				if (!$forceOnlyFirst && ($settingsOption["dateEnds"] == "true" || $forceBoth)) {
					$html .= '<div class="dateWrap" style="margin-top:'.$settingsOption["datePaddingBottom"].'px;"><div class="datelabel" style="'.$settingsOption[$suffix."dateLabel"].'">'.$settingsOption["endsOnTxt"].'</div>';
						$html .= '<div class="date" >'.$dateLanguaged_end.'</div>';
						$html .= '<div class="time" >'.$endTime.'</div>';

					$html .= '</div>';
				}

				$html .= '</div>';

				if ($bookBtn && $id > -1) {
					$html .= '<div class="btns">';
						//check if booked
						global $wpdb;
				    $eventTickets = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by id asc");

				    $hasLeft = false;
				    foreach($eventTickets as $ticketInfo) {
			       if (intval(Event::checkSpots($toOpenDate, $ticketInfo->id)) > 0 ) {
			       		$hasLeft = true;
			       		break;
			       }
				    }

				    if ($hasLeft) {
				    	$modalLink = $id.$dateId;
							$txt = $settingsOption["settings"]->btnTxt;
							$html .= '<a href="#" class="directDateBook" data-modal="offlineBooking'.$modalLink.'" data-id="'.$id.'" data-dateID="'.$dateId.'" data-toOpen="'.$toOpenDate.'">'.$txt.'</a>';
				    } else {
				    	$html .= '<div class="allBooked">'.$settingsOption["bookedTxt"].'</div>';
				    }

					$html .= '</div>';
				}

		$html .= '</div>';
		return $html;
	}

	public static function eventIdentificationClasses ($id) {
		global $wpdb;
		$results = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("categoryEvents")." where event='$id'");

		$classes = '';
		foreach ($results as $result) {
			$classes  .= ' ebpCat_'.$result->category;
		}
		return $classes;
	}

	// VIEW BOOKED FUNCTIONS
	public static function viewBooked ($eventId, $dateId) {
		$html = '';
		$modal = '';
		if (self::getUsersAddonPath() && EventBookingProUsersClass::viewBooked()) {
			$bookedObj = EventBookingProUsersClass::showBookedPeopeForEvent($eventId, $dateId);
			$html .= $bookedObj['btn'];
			$modal .= $bookedObj['modal'];
		}

		return array('html' => $html, 'modal' => $modal);
	}



	// UI HELPERS
	public static function getModalBtn($id, $occur, $date_id, $dateId, $active, $btnStyling, $txt, $notOpenTxt, $bookingEndedTxt, $dateFormat, $timeFormat, $class = 'buy') {
		$html = '';
		$modal = '';

		if ($btnStyling == '') {
			$btnStyling['btn'] = '';
			$btnStyling['cnt'] = '';
		}
		if ($date_id > -1)
			$modalLink = $id.$date_id;
		else
			$modalLink = $id;

		$mdtrigger = ($active) ? 'md-trigger' : '';
		$deactiveClass = (!$active) ? 'deactive' : '';

		if (self::getUsersAddonPath()) {
			$requireLogin = !EventBookingProUsersClass::showBookBtn();
		} else {
			$requireLogin = false;
		}

		$html .= '<div class="'.$class.'" style="'.$btnStyling['cnt'].'">';

		if ($requireLogin) {
			$html .= EventBookingProUsersClass::getRequireLoginHtml();
		} else {
			$bookingStatus = self::bookingOpen($occur);
			if ($bookingStatus == 0) {
				$html .= '<a data-modal= "offlineBooking'.$modalLink.'" data-id="'.$id.'" data-dateID="'.$dateId.'" class= "buyBtn '.$mdtrigger.' '.$deactiveClass.'" style="'.$btnStyling['btn'].'">'.$txt.'</a>';
			} else if ($bookingStatus == 1) {
				$dateFormat = EventBookingHelpers::convertDateFormat($dateFormat);
        $startDate = utf8_encode(strftime($dateFormat, strtotime($occur->startBooking_date)));
        $startTime = date($timeFormat, strtotime($dateRow->startBooking_time));

				$html .= '<cite>'.str_replace(array('%date%', '%time%'), array($startDate, $startTime), $notOpenTxt).'</cite>';

			} else {
				$html .= '<cite>'.$bookingEndedTxt.'</cite>';
			}
		}

		// get booked people
		if ($active) {
			$viewBooked = self::viewBooked($id, $date_id);
			$html .= $viewBooked['html'];
			$modal .= $viewBooked['modal'];
		}

		$html .= '</div>';

		return array('html' => $html, 'modal' => $modal);
	}

	public static function getBookModal($id, $date_id, $ticket, $data) {
			global $wpdb;
			$today = current_time('Y-m-d');

			$settingsOption = EventBookingHelpers::getStyling(600,1);
			$curSymbol = EventBookingHelpers::getSymbol($settingsOption["currency"]);
			$upcomingDates = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("eventDates")." where event= '$id' and  start_date>= '$today' order by start_date asc, start_time asc");

			switch($settingsOption["settings"]->ticketsOrder) {
				case "5":
					$ticketResults = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by cost desc");
		            break;

				case "4":
					$ticketResults = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by cost asc");
					break;

				case "3":
					$ticketResults = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by name desc");
					break;

				case "2":
					$ticketResults = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by name asc");
					break;

				case "1":
				default:
					$ticketResults = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("tickets")." where event= '$id' order by id asc");
			}

			$i = 0;
			foreach($ticketResults as $eventData) {
				if (	$i == 0) {
					$ticketID = $eventData->id;
					$cost = $eventData->cost;
					break;
				}
			}

			if ($date_id > -1)
				$modalLink = $id.$date_id;
			else
				$modalLink = $id;
			$html = '<input name= "ebpmobilepagelink" value="'.get_page_link(get_option('ebp_page_id')).'" type= "hidden"  />';


			$successURL = '';
			if ($settingsOption["settings"]->doAfterSuccess== "redirect")
				$successURL = ' data-successURL="'.$settingsOption["settings"]->doAfterSuccessRedirectURL.'" ';

			$today = current_time('Y-m-d');

			$html .= '<div class= "md-modal md-fullpage" id= "offlineBooking'.$modalLink.'" data-id="'.$modalLink.'" data-afterSuccess="'.$settingsOption["settings"]->doAfterSuccess.'" '.$successURL.' data-priceDecPoint="'.$settingsOption["settings"]->priceDecPoint.'" data-priceThousandsSep="'.$settingsOption["settings"]->priceThousandsSep.'" data-priceDecLength="'.$settingsOption["settings"]->priceDecimalCount.'">';

					$html .= '<div class= "md-content">';
					$html .= '<div class= "closeBtn"><a href= "#" class= " boxBtns">x</a></div>';
					$html .= '<input name= "ajaxlink" value="'.site_url().'" type= "hidden"  />';

					$html .= '<div class= "title" style="'.$settingsOption["modalTitle"].'">'.stripslashes($data->name).'</div>';
					$html .= '<div class= "tickets" data-id="'.$id.'">';

								$date_select_html = '<select id= "ticketDate'.$id.'" class= "cd-select ticketDate">';
								$datesCount = 0;
								foreach($upcomingDates as $dateRow) {
									if ($today == $dateRow->start_date && strtotime(current_time("H:i:s")) >= strtotime($dateRow->start_time)) continue;

                  $datesCount++;
                  $date = new DateTime($dateRow->start_date);
                  $startDate = $date->format($settingsOption["dateFormat"]);

                  $dateFormat = EventBookingHelpers::convertDateFormat($settingsOption["dateFormat"]);
                  $dateLanguaged = utf8_encode(strftime($dateFormat, strtotime($dateRow->start_date)));

		              if ($settingsOption["settings"]->modal_includeTime == "true") {
                    $time_of_event = ','.date($settingsOption["timeFormat"], strtotime($dateRow->start_time));
		              } else {
                    $time_of_event = '';
                  }

                  $bookingStatus = self::bookingOpen($dateRow);
                  $startsTxt = '';
                  $endsTxt = '';

									if ($bookingStatus == 1) {
										$dateFormat = EventBookingHelpers::convertDateFormat($settingsOption["dateFormat"]);
						        $startDate = utf8_encode(strftime($dateFormat, strtotime($dateRow->startBooking_date)));
						        $startTime = date($settingsOption["timeFormat"], strtotime($dateRow->startBooking_time));
										$startsTxt = str_replace(array('%date%', '%time%'), array($startDate, $startTime),  $settingsOption["settings"]->bookingStartsTxts);

									} else if ($bookingStatus > 1){
										$endsTxt = $settingsOption["settings"]->bookingEndedTxt;
									}

                  $isSelected = (intval($date_id) == intval($dateRow->id)) ? 'selected="selected"' : '';

                  $date_select_html .= '<option value="' . $dateRow->id . '" ' . $isSelected . ' data-bookingStatus="'.$bookingStatus.'" data-startsTxt="'.$startsTxt.'" data-endsTxt="'.$endsTxt.'" >' . $dateLanguaged . $time_of_event . '</option>';


                  $date_html = '<input type= "hidden"  name= "ticketDate' . $id . '" value="' . $dateRow->id . '" />' . $dateLanguaged . $time_of_event;

								}

								$date_select_html .= '</select>';

								if ($datesCount > 1)
									$html .= $date_select_html;
								else if ($datesCount == 1)
									$html .= '<div>'.$date_html.'</div>';
								else
									$html .= '<input type= "hidden"  name= "ticketDate'.$id.'" value= "-1" />';

								$ticket_html = '<select id= "ticketType'.$id.'"  class= "cd-select ticketType">';
								$first = 0;

								foreach($ticketResults as $dateRow) {
										$first++;
										$sel = ($first == 1) ? 'selected' : '';
										if (intval($dateRow->cost) == 0) {
											$ticketPrice = $settingsOption["settings"]->freeTxt;
										} else {
											$ticketPrice = EventBookingHelpers::currencyPricingFormat($dateRow->cost,
												$curSymbol, $settingsOption["settings"]->currencyBefore,
												$settingsOption["settings"]->priceDecimalCount,
												$settingsOption["settings"]->priceDecPoint,
												$settingsOption["settings"]->priceThousandsSep);
										}

	                    $ticket_html .= '<option data-cost="'.$dateRow->cost.'"  value="'.$dateRow->id.'" '.$sel.'>';
											$ticket_html .= '<div class= "dateWrap">'.stripslashes($dateRow->name);

											if ($ticketPrice != "" && $ticketPrice != "") $ticket_html .= ' ('.$ticketPrice.')';

									    $ticket_html .= '</div>';
										$ticket_html .= '</option>';

										$ticket_1_html = '<input type= "hidden"  name= "ticketType'.$id.'" data-cost="'.$dateRow->cost.'"  value="'.$dateRow->id.'"/>'.stripslashes($dateRow->name);
										if ($ticketPrice != "")
										    $ticket_1_html .= ' ('.$ticketPrice.')';
								}

								$ticket_html.= '</select>';

								if ($first > 1)
									$html .= $ticket_html;
								else
									$html .= '<div>'.$ticket_1_html.'</div>';


								$left = self::checkSpots($date_id, $ticketID);

								$spotsLeftShow = ($data->showSpots == "true") ? '' : 'display:none';

								$html .= '<div class= "spotsleft" style="'.$spotsLeftShow.'">'.$settingsOption["modalSpotsLeftTxt"].' <span>'.$left.'</span></div>';

								$html .= '<div  class= "topBorder" style= "margin-top:5px;"></div>';
					$html .= '</div>';

					$multipleBookings = ($settingsOption["multipleBookings"] == 'true') ? '' : 'display:none;';
					$html .= '<div class= "optionsCnt" style="'.$multipleBookings.' ">';
						$html .= '<div class= "options" style="'.$settingsOption["modalInputSpace"].'">';
							$html .= '<div class= "optCol">'.$settingsOption["modalQuantityTxt"].'</div>';
							$html .= '<div class= "optCol singleLabel">'.$settingsOption["modalSingleCostTxt"].'</div>';
							$html .= '<div class= "optCol totalLabel">'.$settingsOption["modalTotalCostTxt"].'</div>';
						$html .= '</div>';

						$html .= '<div class= "options" style="'.$settingsOption["modalInputSpace"].' '.$multipleBookings.'">';

							$html .= '<div class= "optCol quantityBtns"><a href= "#" class= " boxBtns down">-</a><span>1</span><a href= "#" class= " boxBtns up">+</a></span></div>';

							$html .= '<div class= "optCol single"><span>'.EventBookingHelpers::currencyPricingFormat($cost,$curSymbol,$settingsOption["settings"]->currencyBefore,$settingsOption["settings"]->priceDecimalCount,$settingsOption["settings"]->priceDecPoint,$settingsOption["settings"]->priceThousandsSep, '<strong>%cost%</strong>').'</span></div>';

							$html .= '<div class= "optCol total"><span>'.	EventBookingHelpers::currencyPricingFormat($cost,$curSymbol,$settingsOption["settings"]->currencyBefore,$settingsOption["settings"]->priceDecimalCount,$settingsOption["settings"]->priceDecPoint,$settingsOption["settings"]->priceThousandsSep, '<strong>%cost%</strong>').'</span></div>';
						$html .= '</div>';
						$html .= '<div  class= "topBorder"></div>';
					$html .= '</div>';

					$isVisible= ($settingsOption["couponsEnabled"] == "true") ? '' : "display:none;";

						$html .= '<div class= "couponDiv" style="'.$isVisible.'">';
							$html .= '<input type= "text" name= "coupon-code" value="'.$settingsOption["settings"]->couponTxt.'" title= "Coupon" class= "couponInput"/>';
							$html .= '<a href= "#" class= "coupon checking">'.$settingsOption["settings"]->applyTxt.'</a>';
							$html .= '<span class= "couponResult"></span>';
							$html .= '<input name= "event-id" value="'.$id.'" type= "hidden"  />';
							$html .= '<input name= "initialCost" value="'.$cost.'" type= "hidden"  />';
							$html .= '<input name= "eventName" value="'.$data->name.'" type= "hidden"  />';
						$html .= '<div  class= "bottomBorder"></div>';
						$html .= '</div>';

					$html .= '<input name= "eventID" value="'.$id.'" type= "hidden"  />';

					$html .= self::getFormHTML($data->form);

					$html .= '<div class="noBuy"></div>';

								$html .= '<div class= "buy" >';
								if ($data->modal== "true") {
									$btndeactive = ($left == 0) ? "deactive" : "";
									$html .= '<a class= "book '.$btndeactive.'" data-type= "site" style="'.$settingsOption["modalBtn"].'"data-id="'.$id.'">'.$settingsOption["modalBookText"].'</a>';
								}
								// gateways
								if (intval($cost)>0) {
									$btndeactive = ($left==0) ? "deactive" : "";

									if ($data->paypal== "true") {
										$html .= '<a class= "book paypal '.$btndeactive.'" data-type= "paypal" style="'.$settingsOption["modalBtn"].'"data-id="'.$id.'">'.$settingsOption["settings"]->paypalBtnTxt.'</a>';
									}

									$gatewayArr = explode("%",$data->gateways);
									foreach($gatewayArr as $gateway) {
										$gatewayData = explode("=",$gateway);

										if (count($gatewayData) > 1 && $gatewayData[1] == "true") {
											$gatewayName = $gatewayData[0];
											$gatewayInfo = $wpdb->get_row( "SELECT * FROM " . EventBookingHelpers::getTableName("gateways")." where name= '$gatewayName' ");
											if ($gatewayInfo->active == 1) {
												$module = $gatewayInfo->module."Helpers";
												require_once(ABSPATH . 'wp-content/plugins/'.$gatewayInfo->module.'/'.$module . ".php");
												$gatwayClass = new $module;
												$html .= '<a class= "book '.$gatewayName.' '.$btndeactive.'"  data-type="'.$gatewayName.'" style="'.$settingsOption["modalBtn"].'"data-id="'.$id.'">'.$gatwayClass->getButtonText().'</a>';
											}
										}
									}
								}

							$html .= '</div>';
							$html .= '<div class= "offlineloader" data-text="'.$settingsOption["bookingTxt"].'" data-text2="'.$settingsOption["settings"]->eventBookedTxt.'" >'.$settingsOption["bookingTxt"].'</div>';

					$html .= '</div></div>';

					if ($settingsOption["settings"]->doAfterSuccess== "popup") {
						$html .= '<div class= "md-modal md-fullpage" id= "successLink'.$modalLink.'">';
						$html .= '<div class= "md-content successPage">';
							$html .= '<div class= "closeBtn"><a href= "#" class= "boxBtns">x</a></div>';
									$html .= '<div class= "successTitle">'.$settingsOption["settings"]->doAfterSuccessTitle.'</div>';
									$html .= '<div class= "successMsg">'.$settingsOption["settings"]->doAfterSuccessMessage.'</div>';
								$html .= '</div>';
						$html .= '</div>';
					}

					return $html;
		}


	public static function getFormHTML($form) {
		global $wpdb;

		$settings = $wpdb->get_row( "SELECT * FROM " . EventBookingHelpers::getTableName("settings")." where id= '1' ");

		$modalInputStyle = 'font-size: '.$settings->modal_input_fontSize.'px; line-height: '.$settings->modal_input_lineHeight.'px;  padding: '.$settings->modal_input_topPadding.'px 0px ; margin: '.$settings->modal_input_space.'px 0px 0px';
		$modalInputSpaceStyle= 'margin: '.$settings->modal_input_space.'px 0px 0px;';
		$modalCheckSpace= 'margin-bottom: '.$settings->checkBoxMarginBottom.'px; margin-top: '.$settings->checkBoxMarginTop.'px;';
		$modalTextAreaStyle= 'font-size: '.$settings->modal_input_fontSize.'px; line-height: '.$settings->modal_input_lineHeight.'px; padding: '.$settings->modal_input_topPadding.'px 0px ;margin-top: '.$settings->modal_input_space.'px; ';


		if (!AddOnManager::usesFormAddOn())
			$isAvalable = -1;
		else {
			$formData = $wpdb->get_row( "SELECT * FROM " . EventBookingHelpers::getTableName("forms")." where id='$form'");
			$isAvalable = ($formData) ? 1 : -1;
		}
		$requiresAccount = false;

		if (self::getUsersAddonPath()) {
			$isLoggedIn = EventBookingProUsersClass::isLoggedIn();
			$requiresAccount = EventBookingProUsersClass::requiresAccount();
			if ($isLoggedIn) {
				$currentUser = EventBookingProUsersClass::getCurrentUser();
			}
		} else {
			$isLoggedIn = false;
		}

		$disableFields = ($isLoggedIn && $requiresAccount) ? 'disabled' : '';

		$emailvalue = ($isLoggedIn) ? $currentUser->user_email : $settings->modalEmailTxt;

		if ($isAvalable >= 0 && $formData->splitName == 'true') {
			$firstNamevalue = ($isLoggedIn) ? $currentUser->user_firstname : $formData->firstNameTxt;
			$lastNamevalue = ($isLoggedIn) ? $currentUser->user_lastname : $formData->lastNameTxt;
		} else {
			$fullNamevalue = ($isLoggedIn) ? $currentUser->user_firstname.' '.$currentUser->user_lastname : $settings->modalNameTxt;
		}

		$html = '<form>';
		if ($isAvalable >= 0 && $formData->splitName == 'true') {
			$html .= '<div>';
				$html .= '<input name="firstName" value="'.$firstNamevalue.'" title="'.$formData->firstNameTxt.'"  class="bookInput half isRequired" style="'.$modalInputStyle.'" type="text"  '.$disableFields.' />';

				$html .= '<input name="lastName" value="'.$lastNamevalue.'" title="'.$formData->lastNameTxt.'"  class="bookInput half isRequired" style="'.$modalInputStyle.'" type="text"  '.$disableFields.' />';
			$html .= '</div>';
		} else {
			$html .= '<input name="name" value="'.$fullNamevalue.'" title="'.$settings->modalNameTxt.'"  class="bookInput isRequired" style="'.$modalInputStyle.'" type="text"  '.$disableFields.' />';
		}


		$html .= '<input  name="email" value="'.$emailvalue.'" title="'.$settings->modalEmailTxt.'" class="bookInput email isRequired" style="'.$modalInputStyle.'" type="text"  '.$disableFields.' />';


		if ($isAvalable > 0) {
				$results = $wpdb->get_results( "SELECT * FROM " . EventBookingHelpers::getTableName("formsInput")." where form= '$form' order by fieldorder asc");

            foreach($results as $field) {

				$isRequired = ($field->required == "true") ? "isRequired" : "";

				switch($field->type) {
					case "txt":
						$html .= '<input name="'.$field->name.'" value="'.stripslashes($field->options).'" title="'.stripslashes($field->options).'"  class= "bookInput '.$isRequired.' " style="'.$modalInputStyle.'" type= "text"  />';
					break;
					case "email":
						$html .= '<input name="'.$field->name.'" value="'.stripslashes($field->options).'" title="'.stripslashes($field->options).'"  class= "bookInput email '.$isRequired.' " style="'.$modalInputStyle.'" type= "text"  />';
					break;
					case "txtArea":
						$html .= '<textarea name="'.$field->name.'" title="'.stripslashes($field->options).'"  class= "bookInput '.$isRequired.' " style="'.$modalTextAreaStyle.'">'.stripslashes($field->options).'</textarea>';
					break;

					case "select":
						$html .= '<div class= "fieldHolder '.$isRequired.'" style="'.$modalInputSpaceStyle.'"><select name="'.$field->name.'" >';
						if ($field->label!= "")
							$html .= '<option value= "none" selected= "selected">'.stripslashes($field->label).'</option>';
						$opts = str_replace(array("\n\r","\n"), "",stripslashes($field->options));
						$split = explode(';', $opts);
						foreach($split as $one) {
							$html .= '<option value="'.$one.'">'.$one.'</option>';
						}
						$html .= '</select></div>';
					break;

					case "check":

						$html .= '<div class= "fieldHolder hasCheckBoxes '.$isRequired.'" data-name="'.$field->name.'" style="'.$modalCheckSpace.'">';
						$opts = str_replace(array("\n\r","\n"), "",stripslashes($field->options));
						$split = explode(';', $opts);
						if (stripslashes($field->label)!= "")
							$html .= '<span class= "label" >'.stripslashes($field->label).'</span>';
						$i = 0;

						foreach($split as $one) {
							$rand = rand();
							if ($one!= "") {
								$html .= '<div class= "inputholder"><div class= "checkBoxStyle"><input id="'.$field->name.$i.$rand.'" type= "checkbox" value="'.$one.'" ><label class= "check"  for="'.$field->name.$i.$rand.'"></label></div>'.$one.'</div>';
								$i++;
							}
						}
						$html .= '</div>';
					break;


					case "radio":
						$html .= '<div class= "fieldHolder '.$isRequired.'" style="'.$modalCheckSpace.'">';
						$opts = str_replace(array("\n\r","\n"), "",stripslashes($field->options));
						$split = explode(';', $opts);
						if (stripslashes($field->label)!= "")
							$html .= '<span class= "label" >'.stripslashes($field->label).'</span>';
							$i = 0;
						foreach($split as $one) {
							if ($one != "") {
								$rand = rand();
								$html .= '<div class= "inputholder"><div class= "checkBoxStyle"><input id="'.$field->name.$i.$rand.'"  name="'.$field->name.'" type= "radio" value="'.$one.'"><label class= "dot" for="'.$field->name.$i.$rand.'"></label></div>'.$one.'</div>';
								$i++;
							}
						}
						$html .= '</div>';
					break;

					case "terms":
						$html .= '<div class= "fieldHolder hasCheckBoxes isTerms '.$isRequired.'" data-name="'.$field->name.'" style="'.$modalCheckSpace.'">';

							$rand = rand();
							$html .= '<div class= "checkBoxStyle">';

							$html .= '<input id="'.$field->id.$rand.'" type= "checkbox" value= "terms" >';

							$html .= '<label class= "check" for="'.$field->id.$rand.'"></label>';

							$html .= '</div>';


								$html .= '<span class= "label" >';
							if (stripslashes($field->label) != "")
								$html .= '<a target="_blank" href="'.stripslashes($field->label).'">';
							$html .= stripslashes($field->name);
							if (stripslashes($field->label) !=  "")
								$html .= '</a>';
							$html .= '</span>';


						$html .= '</div>';
					break;
				}
			}
		} else {
			if ($settings->requirePhone== "true")
				$html .= '<input id="bookPhone" name="phone" value="'.$settings->modalPhoneTxt.'" title="'.$settings->modalPhoneTxt.'" class= "bookInput isRequired" style="'.$modalInputStyle.'" type= "text"  />';
			if ($settings->requireAddress== "true")
				$html .= '<input id= "bookAddress" name="address" value="'.$settings->modalAddressTxt.'" title="'.$settings->modalAddressTxt.'" class= "bookInput isRequired" style="'.$modalInputStyle.'" type= "text"  />';
			}
			$html .= '</form>';
			return $html;
	}
}
?>
