<?php

namespace OCA\Photos\Listener;

use OCA\Photos\Album\AlbumMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

class GroupDeletedListener implements IEventListener {
	private AlbumMapper $albumMapper;

	public function __construct(AlbumMapper $albumMapper) {
		$this->albumMapper = $albumMapper;
	}

	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			return;
		}

		// Get all shared albums for this group:
		$albums_group = $this->albumMapper->getSharedAlbumsForCollaborator($event->getGroup()->getGID(), AlbumMapper::TYPE_GROUP);

		// Get all users of this group:
		$users = $event->getGroup()->getUsers();

		foreach ($users as $user) {
			$uid = $user->getUID();

			// Get all albums shared with this specific user:
			$albums_user = $this->albumMapper->getSharedAlbumsForCollaborator($user->getUID(), AlbumMapper::TYPE_USER);

			// Get all group-shared albums that are not directly shared with the removed user in addition
			$albums = array_udiff($albums_group, $albums_user, fn ($a, $b) => ($a->getId() - $b->getId()));

			// Remove their photos from theses albums:
			foreach ($albums as $album) {
				$this->albumMapper->removeFilesForUser($album->getId(), $user->getUID());
			}
		}

		foreach ($albums_group as $album) {
			$this->albumMapper->deleteGroupFromAlbumCollaboratorsList($event->getGroup()->getGID(), $album->getId());
		}
	}
}
