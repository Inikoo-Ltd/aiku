export default {
	CreateUserSessionProperties(payload : object) {
		return {
			type: "CreateUserSession",
			payload
		};
	},
};