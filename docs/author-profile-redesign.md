# Author profile redesign

Implements the approved compact profile rail and chronological feed design for
ordinary WordPress users. ActivityPub remains responsible for federation identity,
follow controls, counts and the Posts & Replies / Posts filter. No users, passwords,
roles, existing post attribution, or production data are changed by this PR.

The desktop layout uses a 1000px container with a compact details rail; it stacks
below 801px. Deployment and verification notes follow with the implementation.
