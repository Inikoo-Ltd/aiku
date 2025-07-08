import 'dotenv/config';

export default {
  expo: {
    name: "Maya",
    slug: "i-hate-this-job",
    version: "1.0.0",
    scheme: "aiku", // ✅ Add this line
    extra: {
      API_URL: process.env.API_URL,
    },
  },
};
