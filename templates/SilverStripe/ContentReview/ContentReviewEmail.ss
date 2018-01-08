<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head></head>
	<body>
		<table id="Content" cellspacing="1" cellpadding="10">
			<tbody>
				<tr>
					<td scope="row" colspan="2" class="typography">
						$EmailBody
					</td>
				</tr>
				<% loop $Pages %>
				<tr>
					<td valign="top">$Title</td>
					<td><a href="{$BaseURL}admin/pages/edit/show/$ID"><%t SilverStripe\\ContentReview\\Tasks\\ContentReviewEmails.REVIEWPAGELINK 'Review the page in the CMS' %></a><br />
						<a href="$AbsoluteLink"><%t SilverStripe\\ContentReview\\Tasks\\ContentReviewEmails.VIEWPUBLISHEDLINK 'View this page on the website' %></a>
					</td>
				</tr>
				<% end_loop %>
			</tbody>
		</table>
	</body>
</html>
