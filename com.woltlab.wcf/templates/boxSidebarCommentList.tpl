<ul class="sidebarItemList">
	{foreach from=$boxCommentList item=boxComment}
		<li>
			<div class="sidebarItemTitle">
				<h3><a href="{$boxComment->getLink()}">{$boxComment->title}</a></h3>
			</div>
			
			<div class="sidebarCommentContent">
				<small>{@$boxComment->getExcerpt(50)}</small>
			</div>
			<div>
				<small>{user object=$boxComment->getUserProfile()} <span class="separatorLeft">{@$boxComment->time|time}</span></small>
			</div>
		</li>
	{/foreach}
</ul>
