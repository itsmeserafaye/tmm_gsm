ALTER TABLE `operators` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `vehicles` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `routes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `route_cap_schedule` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `terminals` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `drivers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `officers` MODIFY `officer_id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`officer_id`);

ALTER TABLE `coops` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `documents` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `operator_documents` MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`doc_id`);

ALTER TABLE `operator_portal_users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `operator_portal_applications` MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

ALTER TABLE `franchise_applications` MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`application_id`);

ALTER TABLE `endorsement_records` MODIFY `endorsement_id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`endorsement_id`);
