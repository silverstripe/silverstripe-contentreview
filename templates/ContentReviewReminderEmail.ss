<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head></head>
	<body id="Content">
		<table cellspacing="1" cellpadding="10">
			<tbody>
				<tr>
					<td scope="row" colspan="2" class="typography">
						$EmailBodyFirstReminder
					</td>
				</tr>
				<% loop $FirstReminderPages %>
				<tr>
					<td valign="top">$Title</td>
					<td><a href="{$BaseURL}admin/pages/edit/show/$ID"><% _t('ContentReviewEmails.REVIEWPAGELINK','Review the page in the CMS') %></a><br />
						<a href="$AbsoluteLink"><% _t('ContentReviewEmails.VIEWPUBLISHEDLINK','View this page on the website') %></a>
					</td>
				</tr>
				<% end_loop %>
			</tbody>
		</table>

		<table id="Content" cellspacing="1" cellpadding="10">
			<tbody>
				<tr>
					<td scope="row" colspan="2" class="typography">
						$EmailBodySecondReminder
					</td>
				</tr>
				<% loop $SecondReminderPages %>
				<tr>
					<td valign="top">$Title</td>
					<td><a href="{$BaseURL}admin/pages/edit/show/$ID"><% _t('ContentReviewEmails.REVIEWPAGELINK','Review the page in the CMS') %></a><br />
						<a href="$AbsoluteLink"><% _t('ContentReviewEmails.VIEWPUBLISHEDLINK','View this page on the website') %></a>
					</td>
				</tr>
				<% end_loop %>
			</tbody>
		</table>
	</body>
</html>